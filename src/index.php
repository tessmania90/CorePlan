<?php
// /var/www/html/index.php

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/data/php_errors.log');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    if (error_reporting() & $errno) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
});

set_exception_handler(function($e) {
    error_log("Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $request = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request, '/api/') === 0) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Interner Systemfehler. Bitte Logs prüfen.']);
    } else {
        http_response_code(500);
        echo "<h1 style='color:white; font-family:sans-serif; text-align:center; margin-top:50px;'>500 - Systemfehler</h1>";
    }
    exit;
});

header('Content-Type: application/json');

require_once 'database.php';
require_once 'mailer.php'; // NEU
require_once 'auth.php';
require_once 'projects.php';
require_once 'router.php';