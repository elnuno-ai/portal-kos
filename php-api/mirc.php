<?php
// Lightweight mIRC + member-status sync for the XAMPP/MySQL setup.
require __DIR__.'/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metode tidak diizinkan.']);
    exit;
}

function sanitize_mirc_message($raw) {
    if (!is_array($raw)) return null;
    $type = in_array($raw['type'] ?? '', ['chat', 'system', 'action'], true) ? $raw['type'] : 'chat';
    $text = trim((string)($raw['text'] ?? ''));
    if ($text === '') return null;
    $message = [
        'type' => $type,
        'time' => mb_substr((string)($raw['time'] ?? ''), 0, 20),
        'ts' => isset($raw['ts']) && is_numeric($raw['ts']) ? (float)$raw['ts'] : round(microtime(true) * 1000),
        'text' => mb_substr($text, 0, 2000),
    ];
    if (!empty($raw['nick'])) $message['nick'] = mb_substr((string)$raw['nick'], 0, 40);
    if (!empty($raw['role'])) $message['role'] = $raw['role'] === 'admin' ? 'admin' : 'tenant';
    if (!empty($raw['to'])) $message['to'] = mb_substr((string)$raw['to'], 0, 40);
    return $message;
}

try {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS portal_state (
        id TINYINT PRIMARY KEY DEFAULT 1,
        data LONGTEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        $channel = trim((string)($body['channel'] ?? ''));
        $message = sanitize_mirc_message($body['message'] ?? null);
        if (!preg_match('/^[#+@][\w\-.]{1,60}$/u', $channel)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Channel mIRC tidak valid.']);
            exit;
        }
        if (!$message) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Isi pesan kosong atau tidak valid.']);
            exit;
        }

        $row = $pdo->query("SELECT data FROM portal_state WHERE id=1")->fetch();
        $data = $row ? json_decode($row['data'], true) : null;
        if (!is_array($data)) $data = [];
        if (!isset($data['mircChannels']) || !is_array($data['mircChannels'])) $data['mircChannels'] = [];
        if (!isset($data['mircChannels'][$channel]) || !is_array($data['mircChannels'][$channel])) {
            $data['mircChannels'][$channel] = ['topic' => '', 'messages' => []];
        }
        if (!isset($data['mircChannels'][$channel]['messages']) || !is_array($data['mircChannels'][$channel]['messages'])) {
            $data['mircChannels'][$channel]['messages'] = [];
        }
        $data['mircChannels'][$channel]['messages'][] = $message;
        if (count($data['mircChannels'][$channel]['messages']) > 500) {
            $data['mircChannels'][$channel]['messages'] = array_slice($data['mircChannels'][$channel]['messages'], -500);
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($row) {
            $stmt = $pdo->prepare("UPDATE portal_state SET data = ? WHERE id=1");
            $stmt->execute([$json]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO portal_state (id, data) VALUES (1, ?)");
            $stmt->execute([$json]);
        }

        echo json_encode(['ok' => true, 'channel' => $channel, 'message' => $message]);
        exit;
    }

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
