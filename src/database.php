<?php
$dbPath = '/var/www/data/core.sqlite';

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, role TEXT DEFAULT 'user')");
    // Auto-Migration Users
    try { $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT"); } catch (PDOException $e) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, username TEXT, action TEXT, target TEXT, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (config_key TEXT PRIMARY KEY, config_value TEXT NOT NULL)");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER DEFAULT 0, title TEXT NOT NULL, description TEXT, deadline DATETIME, status TEXT DEFAULT 'active', closed_at DATETIME, last_activity DATETIME DEFAULT CURRENT_TIMESTAMP, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    
    // Auto-Migration Projekte
    try { $pdo->exec("ALTER TABLE projects ADD COLUMN closed_at DATETIME"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE projects ADD COLUMN close_comment TEXT"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE projects ADD COLUMN briefing TEXT"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE projects ADD COLUMN assigned_to INTEGER"); } catch (PDOException $e) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, title TEXT NOT NULL, is_completed INTEGER DEFAULT 0, completed_by INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

    // Auto-Migration Tasks
    try { $pdo->exec("ALTER TABLE tasks ADD COLUMN deadline DATETIME"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE tasks ADD COLUMN completed_at DATETIME"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE tasks ADD COLUMN priority INTEGER DEFAULT 2"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE tasks ADD COLUMN phase TEXT DEFAULT 'Standard'"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE tasks ADD COLUMN completion_comment TEXT"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE tasks ADD COLUMN assigned_to INTEGER"); } catch (PDOException $e) {}

    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin', PASSWORD_DEFAULT);
        // Admin ohne Mail initialisieren, kann später gelöscht/neu angelegt werden
        $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)")->execute(['admin', $hash, 'admin']);
    }
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]));
}