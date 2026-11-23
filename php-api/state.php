<?php
// Portal state persistence for the XAMPP/MySQL setup (counterpart of api/state.js).
require __DIR__.'/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS portal_state (
        id TINYINT PRIMARY KEY DEFAULT 1,
        data LONGTEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $row = $pdo->query("SELECT data FROM portal_state WHERE id=1")->fetch();
        respond(['ok' => true, 'state' => $row ? json_decode($row['data'], true) : null]);
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    $state = is_array($input) ? ($input['state'] ?? null) : null;
    if (!is_array($state) || array_is_list($state)) {
        respond(['ok' => false, 'error' => 'Payload state tidak valid.'], 422);
    }

    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (strlen($json) > 4 * 1024 * 1024) {
        respond(['ok' => false, 'error' => 'Data terlalu besar (maksimal 4 MB).'], 413);
    }

    $stmt = $pdo->prepare("INSERT INTO portal_state(id, data) VALUES(1, ?)
        ON DUPLICATE KEY UPDATE data = VALUES(data)");
    $stmt->execute([$json]);

    respond(['ok' => true]);
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => $e->getMessage()], 500);
}
