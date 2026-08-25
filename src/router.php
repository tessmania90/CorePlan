<?php
$request = $_SERVER['REQUEST_URI']; $path = parse_url($request, PHP_URL_PATH); $method = $_SERVER['REQUEST_METHOD'];

if ($path === '/' || $path === '') { header('Content-Type: text/html'); readfile('app.html'); exit;
} elseif ($path === '/api/login' && $method === 'POST') { handleLogin();
} elseif ($path === '/api/logout') { handleLogout();
} elseif ($path === '/api/status' && $method === 'GET') { echo json_encode(['success' => true, 'status' => 'online', 'system' => 'CorePlan']);
} elseif ($path === '/api/logs' && $method === 'GET') { handleGetLogs();
} elseif ($path === '/api/settings' && $method === 'GET') { handleGetSettings();
} elseif ($path === '/api/settings' && $method === 'POST') { handleSaveSettings();
} elseif ($path === '/api/settings/test-smtp' && $method === 'POST') { handleTestSmtp();
} elseif ($path === '/api/settings/test-gotify' && $method === 'POST') { handleTestGotify();
} elseif ($path === '/api/settings/delete' && $method === 'POST') { handleDeleteSetting();
} elseif ($path === '/api/password' && $method === 'POST') { handleChangePassword();
} elseif ($path === '/api/users' && $method === 'GET') { handleGetUsers();
} elseif ($path === '/api/users' && $method === 'POST') { handleCreateUser();
} elseif ($path === '/api/users/edit' && $method === 'POST') { handleEditUser(); // NEU
} elseif ($path === '/api/users/delete' && $method === 'POST') { handleDeleteUser();
// --- COREPLAN ENDPUNKTE ---
} elseif ($path === '/api/projects' && $method === 'GET') { handleGetProjects();
} elseif ($path === '/api/projects' && $method === 'POST') { handleCreateProject();
} elseif ($path === '/api/projects/close' && $method === 'POST') { handleCloseProject();
} elseif ($path === '/api/projects/extend' && $method === 'POST') { handleExtendProject();
} elseif ($path === '/api/projects/assign' && $method === 'POST') { handleAssignProject();
} elseif ($path === '/api/projects/delete' && $method === 'POST') { handleDeleteProject();
} elseif ($path === '/api/tasks' && $method === 'GET') { handleGetTasks();
} elseif ($path === '/api/tasks/create' && $method === 'POST') { handleCreateTask();
} elseif ($path === '/api/tasks/toggle' && $method === 'POST') { handleToggleTask();
} elseif ($path === '/api/tasks/edit' && $method === 'POST') { handleEditTask();
} elseif ($path === '/api/tasks/delete' && $method === 'POST') { handleDeleteTask();
} elseif ($path === '/api/myarea' && $method === 'GET') { handleGetMyArea();
} elseif ($path === '/api/me' && $method === 'GET') {
    @session_start(); if (isset($_SESSION['user_id'])) { echo json_encode(['success' => true, 'username' => $_SESSION['username'], 'role' => $_SESSION['role'], 'user_id' => $_SESSION['user_id']]); } else { http_response_code(401); echo json_encode(['success' => false, 'error' => 'Not authenticated']); } exit;
} else { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Endpoint not found']); }