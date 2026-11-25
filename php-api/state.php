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

    $suppliedRemovals = is_array($input['removedPendingIds'] ?? null) ? $input['removedPendingIds'] : [];

    $pdo->beginTransaction();
    $existingRow = $pdo->query("SELECT data FROM portal_state WHERE id=1 FOR UPDATE")->fetch();
    $existing = $existingRow ? json_decode($existingRow['data'], true) : null;

    if (is_array($existing)) {
        $tombstones = [];
        foreach ([
            $existing['pendingTenantRemovals'] ?? [],
            $state['pendingTenantRemovals'] ?? [],
            $suppliedRemovals,
        ] as $list) {
            if (!is_array($list)) continue;
            foreach ($list as $id) {
                if ($id) $tombstones[(string)$id] = true;
            }
        }
        $tombstones = array_slice(array_keys($tombstones), -200);
        $tombstoneSet = array_flip($tombstones);

        $incomingPending = [];
        foreach ((is_array($state['pendingTenants'] ?? null) ? $state['pendingTenants'] : []) as $item) {
            if (is_array($item) && !empty($item['id']) && !isset($tombstoneSet[$item['id']])) {
                $incomingPending[] = $item;
            }
        }
        $incomingIds = [];
        foreach ($incomingPending as $item) $incomingIds[(string)$item['id']] = true;

        $preservedPending = [];
        foreach ((is_array($existing['pendingTenants'] ?? null) ? $existing['pendingTenants'] : []) as $item) {
            if (!is_array($item) || empty($item['id'])) continue;
            if (isset($tombstoneSet[$item['id']]) || isset($incomingIds[(string)$item['id']])) continue;
            $preservedPending[] = $item;
        }

        $state['pendingTenants'] = array_merge($preservedPending, $incomingPending);
        $state['pendingTenantRemovals'] = $tombstones;

        if ($preservedPending && is_array($state['rooms'] ?? null)) {
            $roomsById = [];
            foreach ($state['rooms'] as $room) {
                if (is_array($room) && isset($room['id'])) $roomsById[(string)$room['id']] = $room;
            }
            foreach ($state['rooms'] as $index => $room) {
                if (!is_array($room) || !isset($room['id'])) continue;
                foreach ($preservedPending as $pending) {
                    if (($pending['roomId'] ?? null) === $room['id'] && ($room['status'] ?? '') === 'available') {
                        $state['rooms'][$index]['status'] = 'pending';
                        $state['rooms'][$index]['tenant'] = $pending['fullName'] ?? null;
                    }
                }
            }
        }
    }

    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (strlen($json) > 4 * 1024 * 1024) {
        $pdo->rollBack();
        respond(['ok' => false, 'error' => 'Data terlalu besar (maksimal 4 MB).'], 413);
    }

    $stmt = $pdo->prepare("INSERT INTO portal_state(id, data) VALUES(1, ?)
        ON DUPLICATE KEY UPDATE data = VALUES(data)");
    $stmt->execute([$json]);
    $pdo->commit();

    respond(['ok' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (Throwable $ignore) { /* abaikan */ }
    }
    respond(['ok' => false, 'error' => $e->getMessage()], 500);
}
