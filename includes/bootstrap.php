<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config/config.php';
if (!is_file($configFile)) {
    if (PHP_SAPI !== 'cli' && strpos($_SERVER['REQUEST_URI'] ?? '', '/install/') === false) {
        header('Location: install/');
        exit;
    }
    $config = require dirname(__DIR__) . '/config/config.sample.php';
} else {
    $config = require $configFile;
}

date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['app']['session_name'] ?? 'hcs_vote_sess');
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

$pdo = db_connect($config);
