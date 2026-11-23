<?php
// Loads portal data from MySQL. Falls back to static portal_data.php if DB is unavailable.
require_once __DIR__.'/db.php';

try {
    $pdo = db();

    $settings = [];
    foreach ($pdo->query("SELECT setting_key, setting_value FROM settings") as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $siteBrandConfig = [
        'logoText' => $settings['logoText'] ?? 'IK',
        'logoImage' => !empty($settings['logoImage']) ? $settings['logoImage'] : null,
        'title' => $settings['title'] ?? 'Ekosistem Hub',
        'subtitle' => $settings['subtitle'] ?? '',
        'footerLeft' => $settings['footerLeft'] ?? '',
        'footerRight' => $settings['footerRight'] ?? '',
    ];

    $ticker = $pdo->query("SELECT * FROM ticker_config WHERE id=1")->fetch() ?: [];
    $tickerConfig = [
        'text' => $ticker['text'] ?? '',
        'speed' => $ticker['speed'] ?? '22s',
        'color' => $ticker['color'] ?? 'amber',
        'active' => !empty($ticker['active']),
    ];

    $tabConfigs = [];
    foreach ($pdo->query("SELECT * FROM tab_configs ORDER BY sort_order, tab_key") as $t) {
        $tab = [
            'key' => $t['tab_key'],
            'navLabel' => $t['nav_label'],
            'sectionTitle' => $t['section_title'],
            'sectionDesc' => $t['section_desc'],
            'isSystem' => !empty($t['is_system']),
        ];
        if (!empty($t['is_sub_section'])) $tab['isSubSection'] = true;
        $tabConfigs[] = $tab;
    }

    $rooms = [];
    foreach ($pdo->query("SELECT * FROM rooms ORDER BY id") as $r) {
        $rooms[] = [
            'id' => $r['id'],
            'name' => $r['name'],
            'floor' => $r['floor'],
            'normalPrice' => (int)$r['normal_price'],
            'discountActive' => !empty($r['discount_active']),
            'promoPrice' => (int)$r['promo_price'],
            'discountUntil' => $r['discount_until'] ?: '',
            'promoLabel' => $r['promo_label'] ?? '',
            'status' => $r['status'],
            'tenant' => $r['tenant_name'],
            'desc' => $r['description'],
            'facilities' => json_decode($r['facilities'] ?: '[]', true) ?: [],
        ];
    }

    $registeredTenants = [];
    $q = "SELECT t.*, r.name AS room_name FROM tenants t LEFT JOIN rooms r ON r.id=t.room_id WHERE t.status='active' ORDER BY t.username";
    foreach ($pdo->query($q) as $t) {
        $registeredTenants[] = [
            'username' => $t['username'],
            'pin' => $t['pin'],
            'wifiPassword' => $t['wifi_password'],
            'fullName' => $t['full_name'],
            'roomId' => $t['room_id'],
            'roomName' => $t['room_name'] ?? '',
            'nickname' => $t['nickname'],
            'phone' => $t['phone'],
            'emergencyPhone' => $t['emergency_phone'],
            'originAddress' => $t['origin_address'],
            'profession' => $t['profession'],
            'workplace' => $t['workplace'],
            'socialHandle' => $t['social_handle'],
            'ktpPhoto' => $t['ktp_photo'],
            'joinDate' => $t['join_date'],
            'billingStatus' => $t['billing_status'],
            'billingAmount' => $t['billing_amount'],
            'billingDue' => $t['billing_due'],
        ];
    }

    $nearbyLocations = [];
    foreach ($pdo->query("SELECT * FROM nearby_locations ORDER BY sort_order, id") as $l) {
        $nearbyLocations[] = [
            'id' => $l['id'], 'icon' => $l['icon'], 'name' => $l['name'],
            'dist' => $l['dist'], 'time' => $l['time_label'], 'desc' => $l['description'],
        ];
    }

    $inviteCodes = [];
    foreach ($pdo->query("SELECT * FROM invite_codes ORDER BY code") as $c) {
        $inviteCodes[] = [
            'code' => $c['code'], 'roomId' => $c['room_id'], 'roomName' => $c['room_name'],
            'nickname' => $c['nickname'], 'used' => !empty($c['used']),
        ];
    }

    $guestbookEntries = [];
    foreach ($pdo->query("SELECT * FROM guestbook_entries ORDER BY id") as $g) {
        $guestbookEntries[] = [
            'id' => $g['id'], 'name' => $g['name'], 'category' => $g['category'],
            'message' => $g['message'], 'photo' => $g['photo'],
            'approved' => !empty($g['approved']), 'date' => $g['entry_date'],
        ];
    }

    $mircChannels = [];
    foreach ($pdo->query("SELECT * FROM mirc_channels ORDER BY channel") as $ch) {
        $mircChannels[$ch['channel']] = ['topic' => $ch['topic'], 'messages' => []];
    }

    $radioStations = [];
    foreach ($pdo->query("SELECT * FROM radio_stations ORDER BY sort_order, id") as $s) {
        $radioStations[] = [
            'id' => $s['id'], 'name' => $s['name'], 'genre' => $s['genre'],
            'url' => $s['url'], 'isLive' => !empty($s['is_live']),
        ];
    }

    return [
        'siteBrandConfig' => $siteBrandConfig,
        'tickerConfig' => $tickerConfig,
        'tabConfigs' => $tabConfigs,
        'rooms' => $rooms,
        'nearbyLocations' => $nearbyLocations,
        'inviteCodes' => $inviteCodes,
        'registeredTenants' => $registeredTenants,
        'alumniArchive' => [],
        'guestbookEntries' => $guestbookEntries,
        'mircChannels' => $mircChannels,
        'radioStations' => $radioStations,
    ];
} catch (Throwable $e) {
    error_log('portal_db fallback: '.$e->getMessage());
    return require __DIR__.'/portal_data.php';
}
