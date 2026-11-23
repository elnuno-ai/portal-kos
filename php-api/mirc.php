<?php
// Lightweight mIRC + member-status sync for the XAMPP/MySQL setup.
require __DIR__.'/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS portal_state (
        id TINYINT PRIMARY KEY DEFAULT 1,
        data LONGTEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $row = $pdo->query("SELECT data FROM portal_state WHERE id=1")->fetch();
    $data = $row ? json_decode($row['data'], true) : null;
    $channels = is_array($data) && isset($data['mircChannels']) && is_array($data['mircChannels'])
        ? $data['mircChannels']
        : null;
    $memberUsernames = null;
    if (is_array($data) && isset($data['registeredTenants']) && is_array($data['registeredTenants'])) {
        $memberUsernames = [];
        foreach ($data['registeredTenants'] as $tenant) {
            if (!empty($tenant['username'])) $memberUsernames[] = $tenant['username'];
        }
    }
    echo json_encode([
        'ok' => true,
        'mircChannels' => $channels,
        'memberUsernames' => $memberUsernames,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
