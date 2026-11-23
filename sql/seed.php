<?php
// Seed MySQL from config/portal_data.php (initial portal data).
require __DIR__.'/../config/db.php';
$pdo = db();
$data = require __DIR__.'/../config/portal_data.php';

$pdo->beginTransaction();
try {
    // brand settings
    foreach ($data['siteBrandConfig'] as $k => $v) {
        $pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
            ->execute([$k, $v === null ? '' : (string)$v]);
    }
    // ticker
    $t = $data['tickerConfig'];
    $pdo->prepare("INSERT INTO ticker_config(id,text,speed,color,active) VALUES(1,?,?,?,?) ON DUPLICATE KEY UPDATE text=VALUES(text),speed=VALUES(speed),color=VALUES(color),active=VALUES(active)")
        ->execute([$t['text'], $t['speed'], $t['color'], $t['active'] ? 1 : 0]);

    // tabs
    foreach ($data['tabConfigs'] as $i => $tab) {
        $pdo->prepare("INSERT INTO tab_configs(tab_key,nav_label,section_title,section_desc,is_system,is_sub_section,sort_order) VALUES(?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE nav_label=VALUES(nav_label),section_title=VALUES(section_title),section_desc=VALUES(section_desc),is_system=VALUES(is_system),is_sub_section=VALUES(is_sub_section),sort_order=VALUES(sort_order)")
            ->execute([$tab['key'], $tab['navLabel'], $tab['sectionTitle'] ?? '', $tab['sectionDesc'] ?? '', !empty($tab['isSystem']) ? 1 : 0, !empty($tab['isSubSection']) ? 1 : 0, $i]);
    }

    // rooms
    foreach ($data['rooms'] as $r) {
        $pdo->prepare("INSERT INTO rooms(id,name,floor,normal_price,discount_active,promo_price,discount_until,promo_label,status,tenant_name,description,facilities)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE name=VALUES(name),floor=VALUES(floor),normal_price=VALUES(normal_price),discount_active=VALUES(discount_active),promo_price=VALUES(promo_price),discount_until=VALUES(discount_until),promo_label=VALUES(promo_label),status=VALUES(status),tenant_name=VALUES(tenant_name),description=VALUES(description),facilities=VALUES(facilities)")
            ->execute([
                $r['id'], $r['name'], $r['floor'], $r['normalPrice'], !empty($r['discountActive']) ? 1 : 0, $r['promoPrice'],
                $r['discountUntil'] ?: null, $r['promoLabel'], $r['status'], $r['tenant'], $r['desc'],
                json_encode($r['facilities'], JSON_UNESCAPED_UNICODE),
            ]);
    }

    // tenants
    foreach ($data['registeredTenants'] as $t) {
        $pdo->prepare("INSERT INTO tenants(username,pin,wifi_password,full_name,room_id,nickname,phone,emergency_phone,origin_address,profession,workplace,social_handle,ktp_photo,join_date,billing_status,billing_amount,billing_due)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE pin=VALUES(pin),wifi_password=VALUES(wifi_password),full_name=VALUES(full_name),room_id=VALUES(room_id),nickname=VALUES(nickname),phone=VALUES(phone),emergency_phone=VALUES(emergency_phone),origin_address=VALUES(origin_address),profession=VALUES(profession),workplace=VALUES(workplace),social_handle=VALUES(social_handle),ktp_photo=VALUES(ktp_photo),join_date=VALUES(join_date),billing_status=VALUES(billing_status),billing_amount=VALUES(billing_amount),billing_due=VALUES(billing_due)")
            ->execute([$t['username'], $t['pin'], $t['wifiPassword'], $t['fullName'], $t['roomId'], $t['nickname'], $t['phone'], $t['emergencyPhone'], $t['originAddress'], $t['profession'], $t['workplace'], $t['socialHandle'], $t['ktpPhoto'], $t['joinDate'], $t['billingStatus'], $t['billingAmount'], $t['billingDue']]);
    }

    // invite codes
    foreach ($data['inviteCodes'] as $c) {
        $pdo->prepare("INSERT INTO invite_codes(code,room_id,room_name,nickname,used) VALUES(?,?,?,?,?)
            ON DUPLICATE KEY UPDATE room_id=VALUES(room_id),room_name=VALUES(room_name),nickname=VALUES(nickname),used=VALUES(used)")
            ->execute([$c['code'], $c['roomId'], $c['roomName'], $c['nickname'], !empty($c['used']) ? 1 : 0]);
    }

    // guestbook
    foreach ($data['guestbookEntries'] as $g) {
        $pdo->prepare("INSERT INTO guestbook_entries(id,name,category,message,photo,approved,entry_date) VALUES(?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE name=VALUES(name),category=VALUES(category),message=VALUES(message),photo=VALUES(photo),approved=VALUES(approved),entry_date=VALUES(entry_date)")
            ->execute([$g['id'], $g['name'], $g['category'], $g['message'], $g['photo'], !empty($g['approved']) ? 1 : 0, $g['date']]);
    }

    // nearby locations
    foreach ($data['nearbyLocations'] as $i => $l) {
        $pdo->prepare("INSERT INTO nearby_locations(id,icon,name,dist,time_label,description,sort_order) VALUES(?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE icon=VALUES(icon),name=VALUES(name),dist=VALUES(dist),time_label=VALUES(time_label),description=VALUES(description),sort_order=VALUES(sort_order)")
            ->execute([$l['id'], $l['icon'], $l['name'], $l['dist'], $l['time'], $l['desc'], $i]);
    }

    // radio
    foreach ($data['radioStations'] as $i => $s) {
        $pdo->prepare("INSERT INTO radio_stations(id,name,genre,url,sort_order) VALUES(?,?,?,?,?)
            ON DUPLICATE KEY UPDATE name=VALUES(name),genre=VALUES(genre),url=VALUES(url),sort_order=VALUES(sort_order)")
            ->execute([$s['id'], $s['name'], $s['genre'], $s['url'], $i]);
    }

    // mirc channels
    foreach ($data['mircChannels'] as $ch => $meta) {
        $pdo->prepare("INSERT INTO mirc_channels(channel,topic) VALUES(?,?) ON DUPLICATE KEY UPDATE topic=VALUES(topic)")
            ->execute([$ch, $meta['topic']]);
    }

    $pdo->commit();
    echo "Seed complete.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "Seed failed: ".$e->getMessage()."\n";
    exit(1);
}
