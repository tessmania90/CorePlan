<?php
// /var/www/html/cron.php
require_once 'database.php';
require_once 'mailer.php';

echo "Starte CorePlan Cronjob...\n";

// Datum exakt in 7 Tagen
$targetDate = date('Y-m-d', strtotime('+7 days'));

// 1. Projekte (Fällig in 7 Tagen)
$stmt = $pdo->query("SELECT p.title, p.deadline, u.email, u.username FROM projects p JOIN users u ON p.assigned_to = u.id WHERE p.status = 'active' AND p.deadline = '$targetDate'");
while($row = $stmt->fetch()) {
    if(!empty($row['email'])) {
        sendSmtpMail($row['email'], "Erinnerung: Projekt '{$row['title']}'", "Hallo {$row['username']},\n\ndas Projekt '{$row['title']}' ist in genau 7 Tagen fällig ({$row['deadline']}).\n\nBitte prüfe die offenen Tasks in CorePlan.\n\nDein CorePlan System");
    }
    sendGotify("Projekt-Deadline", "Projekt '{$row['title']}' (Zugewiesen an: {$row['username']}) ist in 7 Tagen fällig.");
}

// 2. Tasks (Fällig in 7 Tagen)
$stmt = $pdo->query("SELECT t.title, t.deadline, p.title as proj_title, u.email, u.username FROM tasks t JOIN projects p ON t.project_id = p.id JOIN users u ON t.assigned_to = u.id WHERE t.is_completed = 0 AND p.status = 'active' AND t.deadline = '$targetDate'");
while($row = $stmt->fetch()) {
    if(!empty($row['email'])) {
        sendSmtpMail($row['email'], "Erinnerung: To-Do '{$row['title']}'", "Hallo {$row['username']},\n\ndie Aufgabe '{$row['title']}' im Projekt '{$row['proj_title']}' ist in 7 Tagen fällig ({$row['deadline']}).\n\nDein CorePlan System");
    }
    // Gotify auch für Tasks, falls gewünscht.
}

echo "Cronjob erfolgreich beendet.\n";