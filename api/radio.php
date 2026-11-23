<?php
// Radio station management API (JSON). Max 5 stations.
require __DIR__.'/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function all_stations(PDO $pdo): array {
    $rows = [];
    foreach ($pdo->query("SELECT id,name,genre,url,is_live,live_started_at FROM radio_stations ORDER BY sort_order, id") as $s) {
        $rows[] = [
            'id' => $s['id'],
            'name' => $s['name'],
            'genre' => $s['genre'],
            'url' => $s['url'],
            'isLive' => !empty($s['is_live']),
            'liveStartedAt' => $s['live_started_at'],
        ];
    }
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => true, 'stations' => all_stations(db())]);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = $_POST;
$action = $input['action'] ?? '';

try {
    $pdo = db();
    $count = (int)$pdo->query("SELECT COUNT(*) FROM radio_stations")->fetchColumn();

    switch ($action) {
        case 'add': {
            if ($count >= 5) respond(['ok' => false, 'error' => 'Maksimal 5 stasiun radio.'], 422);
            $name = trim((string)($input['name'] ?? ''));
            $url = trim((string)($input['url'] ?? ''));
            if ($name === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                respond(['ok' => false, 'error' => 'Nama dan URL stream yang valid wajib diisi.'], 422);
            }
            $genre = trim((string)($input['genre'] ?? 'Live Stream')) ?: 'Live Stream';
            $isLive = !empty($input['isLive']) ? 1 : 0;
            $id = 'RAD' . substr((string)time(), -6) . random_int(10, 99);
            $sort = $count;
            $pdo->prepare("INSERT INTO radio_stations(id,name,genre,url,is_live,live_started_at,sort_order)
                VALUES(?,?,?,?,?,?,?)")
                ->execute([$id, $name, $genre, $url, $isLive, $isLive ? date('Y-m-d H:i:s') : null, $sort]);
            respond(['ok' => true, 'id' => $id, 'stations' => all_stations($pdo)]);
        }

        case 'delete': {
            if ($count <= 1) respond(['ok' => false, 'error' => 'Minimal harus ada 1 stasiun.'], 422);
            $id = (string)($input['id'] ?? '');
            $pdo->prepare("DELETE FROM radio_stations WHERE id=?")->execute([$id]);
            respond(['ok' => true, 'stations' => all_stations($pdo)]);
        }

        case 'toggle_live': {
            $id = (string)($input['id'] ?? '');
            $live = !empty($input['live']) ? 1 : 0;
            $pdo->prepare("UPDATE radio_stations SET is_live=?, live_started_at=? WHERE id=?")
                ->execute([$live, $live ? date('Y-m-d H:i:s') : null, $id]);
            respond(['ok' => true, 'stations' => all_stations($pdo)]);
        }

        case 'update': {
            $id = (string)($input['id'] ?? '');
            $name = trim((string)($input['name'] ?? ''));
            $url = trim((string)($input['url'] ?? ''));
            $genre = trim((string)($input['genre'] ?? ''));
            if ($name === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                respond(['ok' => false, 'error' => 'Nama dan URL stream yang valid wajib diisi.'], 422);
            }
            $pdo->prepare("UPDATE radio_stations SET name=?, url=?, genre=? WHERE id=?")
                ->execute([$name, $url, $genre ?: 'Live Stream', $id]);
            respond(['ok' => true, 'stations' => all_stations($pdo)]);
        }

        default:
            respond(['ok' => false, 'error' => 'Aksi tidak dikenal.'], 400);
    }
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => $e->getMessage()], 500);
}
