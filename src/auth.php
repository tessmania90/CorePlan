<?php
// /var/www/html/auth.php

function handleLogin() {
    global $pdo; @session_start();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$user['id'], $user['username'], 'User Login', $_SERVER['REMOTE_ADDR']]);
        echo json_encode(['success' => true]);
    } else { echo json_encode(['success' => false]); }
    exit;
}

function handleLogout() {
    global $pdo; @session_start();
    if (isset($_SESSION['user_id'])) { $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'User Logout', 'System']); }
    session_destroy(); echo json_encode(['success' => true]); exit;
}

function handleGetLogs() {
    global $pdo; @session_start();
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit; }
    $stmt = $pdo->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 50");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]); exit;
}

function handleGetSettings() {
    global $pdo; @session_start();
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit; }
    $stmt = $pdo->query("SELECT * FROM settings ORDER BY config_key ASC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]); exit;
}

function handleSaveSettings() {
    global $pdo; @session_start();
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit; }
    $key = trim($_POST['config_key'] ?? ''); $value = trim($_POST['config_value'] ?? '');
    if ($key === '') { echo json_encode(['success' => false, 'error' => 'Schlüssel darf nicht leer sein.']); exit; }
    $pdo->prepare("REPLACE INTO settings (config_key, config_value) VALUES (?, ?)")->execute([$key, $value]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'Setting Update', "Key: $key"]);
    echo json_encode(['success' => true]); exit;
}

function handleDeleteSetting() {
    global $pdo; @session_start();
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit; }
    $key = trim($_POST['config_key'] ?? '');
    $pdo->prepare("DELETE FROM settings WHERE config_key = ?")->execute([$key]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'Setting gelöscht', "Key: $key"]);
    echo json_encode(['success' => true]); exit;
}

function handleChangePassword() {
    global $pdo; @session_start();
    $userId = $_SESSION['user_id'] ?? 0; if (!$userId) { http_response_code(401); exit; }
    $oldPass = $_POST['old_password'] ?? ''; $newPass = $_POST['new_password'] ?? '';
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?"); $stmt->execute([$userId]); $user = $stmt->fetch();
    if ($user && password_verify($oldPass, $user['password_hash'])) {
        if (strlen($newPass) < 4) { echo json_encode(['success' => false, 'error' => 'Min 4 Zeichen.']); exit; }
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $userId]);
        $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$userId, $_SESSION['username'], 'Passwort geändert', 'Self-Service']);
        echo json_encode(['success' => true]);
    } else { echo json_encode(['success' => false, 'error' => 'Altes Passwort falsch.']); }
    exit;
}

function handleGetUsers() {
    global $pdo; @session_start();
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit; }
    $stmt = $pdo->query("SELECT id, username, email, role FROM users ORDER BY id ASC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]); exit;
}

function handleCreateUser() {
    global $pdo; @session_start();
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit; }
    
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    if (!in_array($role, ['admin', 'pm', 'user'])) $role = 'user';
    
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)")->execute([$username, $email, $hash, $role]);
        $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'User erstellt', "User: $username ($role)"]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Fehler (evtl. existiert der Name schon).']);
    }
    exit;
}

function handleEditUser() {
    global $pdo; @session_start();
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit; }
    
    $id = $_POST['id'] ?? 0;
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $password = $_POST['password'] ?? ''; 
    
    if (!in_array($role, ['admin', 'pm', 'user'])) $role = 'user';
    
    try {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, password_hash = ? WHERE id = ?")->execute([$username, $email, $role, $hash, $id]);
        } else {
            $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?")->execute([$username, $email, $role, $id]);
        }
        
        // BUGFIX: Alte Logeinträge und aktuelle Session bei Namensänderung aktualisieren
        $pdo->prepare("UPDATE audit_logs SET username = ? WHERE user_id = ?")->execute([$username, $id]);
        if ($id == $_SESSION['user_id']) { $_SESSION['username'] = $username; }

        $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'User bearbeitet', "User ID: $id ($username)"]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Fehler beim Speichern (evtl. Benutzername schon vergeben).']);
    }
    exit;
}

function handleDeleteUser() {
    global $pdo; @session_start();
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit; }
    $deleteId = $_POST['id'] ?? 0;
    if ($deleteId == $_SESSION['user_id']) { echo json_encode(['success' => false, 'error' => 'Du kannst dich nicht selbst löschen.']); exit; }
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$deleteId]);
    $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$_SESSION['user_id'], $_SESSION['username'], 'User gelöscht', "User ID: $deleteId"]);
    echo json_encode(['success' => true]); exit;
}

function handleTestSmtp() {
    global $pdo; @session_start();
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit; }
    
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?"); $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if(!$user || empty($user['email'])) { echo json_encode(['success' => false, 'error' => 'Dein Admin-Account hat keine E-Mail-Adresse. Bitte bearbeite deinen Account unter "Benutzer".']); exit; }

    $res = sendSmtpMail($user['email'], "CorePlan SMTP Test", "Hallo!\n\nDein CorePlan SMTP-Server (SSL/TLS) funktioniert einwandfrei.\n\nBeste Grüße,\nCorePlan System");
    if($res === true) echo json_encode(['success' => true]); else echo json_encode(['success' => false, 'error' => $res]);
    exit;
}

function handleTestGotify() {
    global $pdo; @session_start();
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit; }
    $res = sendGotify("Test Alarm", "Hallo Admin, Gotify Kommunikation funktioniert!");
    if($res === true) echo json_encode(['success' => true]); else echo json_encode(['success' => false, 'error' => $res]);
    exit;
}

// --- NEU: PASSWORD RECOVERY SYSTEM ---
function handleForgotPassword() {
    global $pdo;
    $email = trim($_POST['email'] ?? '');
    if (empty($email)) { echo json_encode(['success' => true]); exit; } // Anti-Enumeration

    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 Std
        $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")->execute([$token, $expires, $user['id']]);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $resetLink = $protocol . $_SERVER['HTTP_HOST'] . "/?reset_token=" . $token;

        $subject = "CorePlan - Passwort zurücksetzen";
        $message = "Hallo " . $user['username'] . ",\n\njemand hat das Zurücksetzen deines Passworts angefordert.\n\nKlicke auf diesen Link, um ein neues Passwort zu vergeben (der Link ist 60 Minuten gültig):\n$resetLink\n\nFalls du das nicht warst, kannst du diese E-Mail einfach ignorieren.\n\nDein CorePlan System";
        
        sendSmtpMail($email, $subject, $message);
        $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$user['id'], $user['username'], 'Password Reset angefordert', $_SERVER['REMOTE_ADDR']]);
    }
    echo json_encode(['success' => true]); exit;
}

function handleResetPassword() {
    global $pdo;
    $token = trim($_POST['reset_token'] ?? '');
    $newPass = $_POST['new_password'] ?? '';

    if (empty($token) || strlen($newPass) < 4) { echo json_encode(['success' => false, 'error' => 'Ungültige Eingabe oder Passwort zu kurz.']); exit; }

    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE reset_token = ? AND reset_expires > CURRENT_TIMESTAMP");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")->execute([$hash, $user['id']]);
        $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, target) VALUES (?, ?, ?, ?)")->execute([$user['id'], $user['username'], 'Password erfolgreich resettet', $_SERVER['REMOTE_ADDR']]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Token ungültig oder abgelaufen.']);
    }
    exit;
}
?>