<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('KOS_DB_HOST') ?: '127.0.0.1';
        $name = getenv('KOS_DB_NAME') ?: 'kos_portal';
        $user = getenv('KOS_DB_USER') ?: 'root';
        $pass = getenv('KOS_DB_PASS') ?: '';
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
