<?php
function getProjectLimit() {
    global $pdo;
    $stmt = $pdo->query("SELECT config_value FROM settings WHERE config_key = 'LICENSE_KEY'");
    $license = $stmt->fetchColumn();
    if (!$license) return 3; 
    $parts = explode('.', $license);
    if (count($parts) !== 2) return 3;
    $payload = json_decode(base64_decode($parts[0]), true);
    $secret = 'TESSMANN_COREPLAN_SECURE_2026_MASTER_KEY';
    $expectedSig = hash_hmac('sha256', $parts[0], $secret);
    if (!hash_equals($expectedSig, $parts[1])) return 3;
    if (isset($payload['expires']) && strtotime($payload['expires']) < time()) return 3;
    return (int)($payload['limit'] ?? 3);
}

function isPMorAdmin() { return in_array($_SESSION['role'] ?? '', ['admin', 'pm']); }

function handleGetProjects() {
    global $pdo; @session_start();
    if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
    $stmt = $pdo->query("
        SELECT p.*, u.username as assigned_username,
               (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as total_tasks,
               (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND is_completed = 1) as completed_tasks
        FROM projects p 
        LEFT JOIN users u ON p.assigned_to = u.id
        ORDER BY CASE WHEN p.status = 'closed' THEN 1 WHEN p.status = 'canceled' THEN 2 ELSE 0 END, p.deadline ASC, p.id DESC
    ");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]); exit;
}

function handleCreateProject() {
    global $pdo; @session_start();
    if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }

    $activeCount = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'active'")->fetchColumn();
    $limit = getProjectLimit();
    if ($activeCount >= $limit) {
        $tierName = $limit === 3 ? "Community Edition" : "Standard Edition";
        echo json_encode(['success' => false, 'error' => "Lizenzlimit der $tierName erreicht."]); exit;
    }

    $title = trim($_POST['title'] ?? ''); 
    $description = trim($_POST['description'] ?? ''); 
    $briefing = trim($_POST['briefing'] ?? ''); 
    $deadline = trim($_POST['deadline'] ?? ''); 
    $assignedTo = trim($_POST['assigned_to'] ?? '');

    if ($title === '') { echo json_encode(['success' => false, 'error' => 'Titel darf nicht leer sein.']); exit; }
    if ($deadline === '') $deadline = null;
    if ($assignedTo === '') $assignedTo = $_SESSION['user_id'];

    $pdo->prepare("INSERT INTO projects (title, description, briefing, deadline, assigned_to) VALUES (?, ?, ?, ?, ?)")->execute([$title, $description, $briefing, $deadline, $assignedTo]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'Projekt erstellt', "Projekt: $title"]);
    echo json_encode(['success' => true]); exit;
}

function handleAssignProject() {
    global $pdo; @session_start();
    if (!isPMorAdmin()) { echo json_encode(['success' => false, 'error' => 'Keine Berechtigung.']); exit; }
    $id = $_POST['id'] ?? 0;
    $pdo->prepare("UPDATE projects SET assigned_to = ?, last_activity = CURRENT_TIMESTAMP WHERE id = ?")->execute([$_SESSION['user_id'], $id]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'Projekt zugewiesen', "ID: $id"]);
    echo json_encode(['success' => true]); exit;
}

function handleExtendProject() {
    global $pdo; @session_start();
    if (!isPMorAdmin()) { echo json_encode(['success' => false, 'error' => 'Keine Berechtigung.']); exit; }
    $id = $_POST['id'] ?? 0; $deadline = trim($_POST['deadline'] ?? ''); if ($deadline === '') $deadline = null;
    $pdo->prepare("UPDATE projects SET deadline = ?, last_activity = CURRENT_TIMESTAMP WHERE id = ? AND status = 'active'")->execute([$deadline, $id]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'Projekt verlängert', "ID: $id"]);
    echo json_encode(['success' => true]); exit;
}

function handleCloseProject() {
    global $pdo; @session_start();
    if (!isPMorAdmin()) { echo json_encode(['success' => false, 'error' => 'Keine Berechtigung.']); exit; }
    $id = $_POST['id'] ?? 0; $status = $_POST['status'] ?? 'closed'; $comment = trim($_POST['comment'] ?? '');
    if (!in_array($status, ['closed', 'canceled'])) $status = 'closed';
    $pdo->prepare("UPDATE projects SET status = ?, closed_at = CURRENT_TIMESTAMP, close_comment = ? WHERE id = ? AND status = 'active'")->execute([$status, $comment, $id]);
    $actionLog = $status === 'canceled' ? 'Projekt abgebrochen' : 'Projekt abgeschlossen';
    $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], $actionLog, "ID: $id"]);
    echo json_encode(['success' => true]); exit;
}

function handleDeleteProject() {
    global $pdo; @session_start();
    if (($_SESSION['role'] ?? '') !== 'admin') { echo json_encode(['success' => false, 'error' => 'Nur Admins dürfen löschen.']); exit; } 
    $id = $_POST['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT status, closed_at FROM projects WHERE id = ?"); $stmt->execute([$id]); $proj = $stmt->fetch();
    if (!$proj) { echo json_encode(['success' => false, 'error' => 'Projekt nicht gefunden.']); exit; }
    if ($proj['status'] === 'active') { echo json_encode(['success' => false, 'error' => 'Aktive Projekte können nicht gelöscht werden.']); exit; }
    if ($proj['status'] === 'closed') {
        $closedAt = strtotime($proj['closed_at']); $sixtyDaysAgo = strtotime('-60 days');
        if ($closedAt > $sixtyDaysAgo) { $daysLeft = ceil(($closedAt - $sixtyDaysAgo) / 86400); echo json_encode(['success' => false, 'error' => "Löschsperre aktiv. Erfolgreiche Projekte erst in $daysLeft Tag(en) löschbar."]); exit; }
    }
    $pdo->prepare("DELETE FROM tasks WHERE project_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'Projekt gelöscht', "ID: $id"]);
    echo json_encode(['success' => true]); exit;
}

function handleGetTasks() {
    global $pdo; @session_start();
    if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
    $projectId = $_GET['project_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT t.*, u.username as assigned_username FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.project_id = ? ORDER BY t.is_completed ASC, t.phase ASC, t.priority ASC, t.deadline ASC, t.id ASC");
    $stmt->execute([$projectId]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]); exit;
}

function handleCreateTask() {
    global $pdo; @session_start();
    if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }

    $projectId = $_POST['project_id'] ?? 0; $title = trim($_POST['title'] ?? ''); $deadline = trim($_POST['deadline'] ?? ''); $phase = trim($_POST['phase'] ?? 'Standard'); $priority = (int)($_POST['priority'] ?? 2); $assignedTo = trim($_POST['assigned_to'] ?? '');
    if ($deadline === '') $deadline = null; if ($phase === '') $phase = 'Standard'; if ($assignedTo === '') $assignedTo = null;
    
    if ($title !== '') {
        $pdo->prepare("INSERT INTO tasks (project_id, title, deadline, phase, priority, assigned_to) VALUES (?, ?, ?, ?, ?, ?)")->execute([$projectId, $title, $deadline, $phase, $priority, $assignedTo]);
        $pdo->prepare("UPDATE projects SET last_activity = CURRENT_TIMESTAMP WHERE id = ?")->execute([$projectId]);
        $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'Task erstellt', "Proj-ID: $projectId | Task: $title"]);
    }
    echo json_encode(['success' => true]); exit;
}

function handleEditTask() {
    global $pdo; @session_start();
    if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }

    $taskId = $_POST['task_id'] ?? 0; $title = trim($_POST['title'] ?? ''); $deadline = trim($_POST['deadline'] ?? ''); $phase = trim($_POST['phase'] ?? 'Standard'); $priority = (int)($_POST['priority'] ?? 2); $assignedTo = trim($_POST['assigned_to'] ?? '');
    if ($deadline === '') $deadline = null; if ($phase === '') $phase = 'Standard'; if ($assignedTo === '') $assignedTo = null;
    
    if ($title !== '') {
        $pdo->prepare("UPDATE tasks SET title = ?, deadline = ?, phase = ?, priority = ?, assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$title, $deadline, $phase, $priority, $assignedTo, $taskId]);
        $stmt = $pdo->prepare("SELECT project_id FROM tasks WHERE id = ?"); $stmt->execute([$taskId]); $t = $stmt->fetch();
        if ($t) $pdo->prepare("UPDATE projects SET last_activity = CURRENT_TIMESTAMP WHERE id = ?")->execute([$t['project_id']]);
        $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'Task bearbeitet', "Task ID: $taskId ($title)"]);
    }
    echo json_encode(['success' => true]); exit;
}

function handleToggleTask() {
    global $pdo; @session_start();
    if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
    $taskId = $_POST['task_id'] ?? 0; $projectId = $_POST['project_id'] ?? 0; $comment = trim($_POST['comment'] ?? '');
    $stmt = $pdo->prepare("SELECT is_completed, title FROM tasks WHERE id = ?"); $stmt->execute([$taskId]); $task = $stmt->fetch();
    if ($task) {
        if ($task['is_completed'] == 0) {
            $pdo->prepare("UPDATE tasks SET is_completed = 1, completed_by = ?, completed_at = CURRENT_TIMESTAMP, completion_comment = ? WHERE id = ?")->execute([$_SESSION['user_id'], $comment, $taskId]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'Task erledigt', "Task: " . $task['title']]);
        } else {
            $pdo->prepare("UPDATE tasks SET is_completed = 0, completed_by = NULL, completed_at = NULL, completion_comment = NULL WHERE id = ?")->execute([$taskId]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'Task reaktiviert', "Task: " . $task['title']]);
        }
        $pdo->prepare("UPDATE projects SET last_activity = CURRENT_TIMESTAMP WHERE id = ?")->execute([$projectId]);
    }
    echo json_encode(['success' => true]); exit;
}

function handleDeleteTask() {
    global $pdo; @session_start();
    if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
    $taskId = $_POST['task_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT project_id, title FROM tasks WHERE id = ?"); $stmt->execute([$taskId]); $task = $stmt->fetch();
    if ($task) {
        $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$taskId]);
        $pdo->prepare("UPDATE projects SET last_activity = CURRENT_TIMESTAMP WHERE id = ?")->execute([$task['project_id']]);
        $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'Task gelöscht', "Task: " . $task['title']]);
    }
    echo json_encode(['success' => true]); exit;
}

function handleGetMyArea() {
    global $pdo; @session_start();
    if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
    $userId = $_SESSION['user_id'];
    
    $stmtProj = $pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as total_tasks, (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND is_completed = 1) as completed_tasks FROM projects p WHERE assigned_to = ? AND status = 'active' ORDER BY deadline ASC");
    $stmtProj->execute([$userId]);
    $myProjects = $stmtProj->fetchAll();
    
    $stmtTasks = $pdo->prepare("SELECT t.*, p.title as project_title FROM tasks t JOIN projects p ON t.project_id = p.id WHERE t.assigned_to = ? AND t.is_completed = 0 AND p.status = 'active' ORDER BY t.priority ASC, t.deadline ASC");
    $stmtTasks->execute([$userId]);
    $myTasks = $stmtTasks->fetchAll();

    echo json_encode(['success' => true, 'projects' => $myProjects, 'tasks' => $myTasks]); exit;
}

// NEU: Task Notizen laden
function handleGetTaskComments() {
    global $pdo; @session_start();
    if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
    $taskId = $_GET['task_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT * FROM task_comments WHERE task_id = ? ORDER BY created_at ASC");
    $stmt->execute([$taskId]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]); exit;
}

// NEU: Task Notiz hinzufügen
function handleAddTaskComment() {
    global $pdo; @session_start();
    if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
    $taskId = $_POST['task_id'] ?? 0;
    $comment = trim($_POST['comment'] ?? '');
    
    if ($comment !== '') {
        $pdo->prepare("INSERT INTO task_comments (task_id, username, comment) VALUES (?, ?, ?)")->execute([$taskId, $_SESSION['username'], $comment]);
        
        $stmt = $pdo->prepare("SELECT project_id FROM tasks WHERE id = ?"); 
        $stmt->execute([$taskId]);
        if ($t = $stmt->fetch()) {
            $pdo->prepare("UPDATE projects SET last_activity = CURRENT_TIMESTAMP WHERE id = ?")->execute([$t['project_id']]);
        }
    }
    echo json_encode(['success' => true]); exit;
}
?>