<?php
/**
 * Import data into MySQL from a CSV file or a published Google Sheet.
 *
 * Usage:
 *   php lib/import.php tenants  data/tenants.csv
 *   php lib/import.php rooms    "https://docs.google.com/spreadsheets/d/<ID>/edit#gid=0"
 *   php lib/import.php guestbook data/guestbook.csv
 *   php lib/import.php promos   data/promos.csv
 *   php lib/import.php menu     data/menu.csv
 *   php lib/import.php nearby   data/nearby.csv
 *   php lib/import.php radio    data/radio.csv
 *   php lib/import.php invite   data/invite.csv
 *
 * Google Sheet must be shared "Anyone with the link" (Viewer).
 */
require __DIR__.'/../config/db.php';

function sheet_to_csv_url(string $url): string {
    if (!preg_match('#docs\.google\.com/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m)) return $url;
    $id = $m[1];
    $gid = '0';
    if (preg_match('/[#?&]gid=(\d+)/', $url, $g)) $gid = $g[1];
    return "https://docs.google.com/spreadsheets/d/$id/export?format=csv&gid=$gid";
}

function read_csv_rows(string $source): array {
    if (preg_match('#^https?://#i', $source)) {
        $source = sheet_to_csv_url($source);
        $ctx = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1]]);
        $csv = @file_get_contents($source, false, $ctx);
        if ($csv === false) throw new RuntimeException("Cannot download: $source (pastikan sheet di-share 'Anyone with the link')");
    } else {
        if (!is_file($source)) throw new RuntimeException("File not found: $source");
        $csv = file_get_contents($source);
    }
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $csv);
    rewind($fh);
    $rows = [];
    $header = null;
    while (($cols = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if ($header === null) {
            $header = array_map(fn($h) => strtolower(trim(preg_replace('/\s+/', '_', $h))), $cols);
            continue;
        }
        if (count(array_filter($cols, fn($v) => trim((string)$v) !== '')) === 0) continue;
        $row = [];
        foreach ($header as $i => $key) $row[$key] = trim((string)($cols[$i] ?? ''));
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

function col(array $row, array $aliases): string {
    foreach ($aliases as $a) {
        if (isset($row[$a]) && $row[$a] !== '') return $row[$a];
    }
    return '';
}
function to_bool(string $v): int {
    return in_array(strtolower(trim($v)), ['1','true','yes','ya','y','aktif','active','approved','sudah','lunas','terisi','used'], true) ? 1 : 0;
}
function to_date(?string $v): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : null;
}
function map_room_status(string $v): string {
    $v = strtolower(trim($v));
    if (in_array($v, ['occupied','terisi','isi','aktif'])) return 'occupied';
    if (in_array($v, ['renovation','renovasi','maintenance','perbaikan'])) return 'renovation';
    return 'available';
}

$table = $argv[1] ?? '';
$source = $argv[2] ?? '';
if (!$table || !$source) {
    fwrite(STDERR, "Usage: php lib/import.php <tenants|rooms|guestbook|promos|menu|nearby|radio|invite> <csv-or-sheet-url>\n");
    exit(2);
}

try {
    $rows = read_csv_rows($source);
    $pdo = db();
    $pdo->beginTransaction();
    $count = 0;

    foreach ($rows as $r) {
        switch ($table) {
            case 'tenants':
                $roomId = col($r, ['room_id','kamar','id_kamar','room']);
                if ($roomId !== '') {
                    $exists = $pdo->prepare("SELECT 1 FROM rooms WHERE id=?");
                    $exists->execute([$roomId]);
                    if (!$exists->fetchColumn()) $roomId = null;
                } else $roomId = null;
                $pdo->prepare("INSERT INTO tenants(username,pin,wifi_password,full_name,room_id,nickname,phone,emergency_phone,origin_address,profession,workplace,social_handle,ktp_photo,join_date,billing_status,billing_amount,billing_due,status)
                    VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE pin=VALUES(pin),wifi_password=VALUES(wifi_password),full_name=VALUES(full_name),room_id=VALUES(room_id),nickname=VALUES(nickname),phone=VALUES(phone),emergency_phone=VALUES(emergency_phone),origin_address=VALUES(origin_address),profession=VALUES(profession),workplace=VALUES(workplace),social_handle=VALUES(social_handle),ktp_photo=VALUES(ktp_photo),join_date=VALUES(join_date),billing_status=VALUES(billing_status),billing_amount=VALUES(billing_amount),billing_due=VALUES(billing_due)")
                    ->execute([
                        col($r, ['username','user','id_penghuni']) ?: ('tenant'.($count+1)),
                        col($r, ['pin']),
                        col($r, ['wifi_password','password_wifi','wifi']),
                        col($r, ['full_name','nama','nama_lengkap','nama_penghuni']),
                        $roomId,
                        col($r, ['nickname','nick']),
                        col($r, ['phone','no_hp','whatsapp','wa']),
                        col($r, ['emergency_phone','kontak_darurat']),
                        col($r, ['origin_address','alamat','alamat_asal']),
                        col($r, ['profession','pekerjaan']),
                        col($r, ['workplace','tempat_kerja','instansi']),
                        col($r, ['social_handle','instagram','sosmed']),
                        col($r, ['ktp_photo','foto_ktp']),
                        col($r, ['join_date','tanggal_masuk']),
                        col($r, ['billing_status','status_bayar','status']),
                        col($r, ['billing_amount','nominal','biaya']),
                        col($r, ['billing_due','jatuh_tempo']),
                        'active',
                    ]);
                break;

            case 'rooms':
                $facilities = col($r, ['facilities','fasilitas']);
                $facJson = json_encode(array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $facilities)))), JSON_UNESCAPED_UNICODE);
                $pdo->prepare("INSERT INTO rooms(id,name,floor,normal_price,discount_active,promo_price,discount_until,promo_label,status,tenant_name,description,facilities)
                    VALUES(?,?,?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE name=VALUES(name),floor=VALUES(floor),normal_price=VALUES(normal_price),discount_active=VALUES(discount_active),promo_price=VALUES(promo_price),discount_until=VALUES(discount_until),promo_label=VALUES(promo_label),status=VALUES(status),tenant_name=VALUES(tenant_name),description=VALUES(description),facilities=VALUES(facilities)")
                    ->execute([
                        col($r, ['id','kode','kode_kamar']),
                        col($r, ['name','nama','nama_kamar']),
                        col($r, ['floor','lantai']),
                        (float)(col($r, ['normal_price','harga','harga_normal','price']) ?: 0),
                        to_bool(col($r, ['discount_active','promo_aktif'])),
                        (float)(col($r, ['promo_price','harga_promo']) ?: 0),
                        to_date(col($r, ['discount_until','promo_sampai'])),
                        col($r, ['promo_label','label_promo']),
                        map_room_status(col($r, ['status'])),
                        col($r, ['tenant_name','tenant','penghuni']),
                        col($r, ['description','deskripsi']),
                        $facJson,
                    ]);
                break;

            case 'guestbook':
                $pdo->prepare("INSERT INTO guestbook_entries(id,name,category,message,photo,approved,entry_date) VALUES(?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE name=VALUES(name),category=VALUES(category),message=VALUES(message),photo=VALUES(photo),approved=VALUES(approved),entry_date=VALUES(entry_date)")
                    ->execute([
                        col($r, ['id']) ?: ('GB'.(count($rows)+$count+1)),
                        col($r, ['name','nama']),
                        col($r, ['category','kategori']),
                        col($r, ['message','pesan','isi']),
                        col($r, ['photo','foto']),
                        to_bool(col($r, ['approved','status'])),
                        col($r, ['date','tanggal']) ?: date('d M Y'),
                    ]);
                break;

            case 'promos':
            case 'menu':
                $tbl = $table === 'menu' ? 'sedhah_menu' : 'promos';
                $id = col($r, ['id']) ?: ($table.'-'.($count+1));
                $nameField = $table === 'menu' ? col($r, ['name','nama']) : col($r, ['title','judul','nama']);
                $pdo->prepare("INSERT INTO $tbl(id,".($table==='menu'?'name':'title').",category,badge,description,normal_price,unit_label,discount_active,promo_price,discount_until,promo_label,image)
                    VALUES(?,?,?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE ".(($table==='menu')?'name':'title')."=VALUES(".(($table==='menu')?'name':'title')."),category=VALUES(category),badge=VALUES(badge),description=VALUES(description),normal_price=VALUES(normal_price),unit_label=VALUES(unit_label),discount_active=VALUES(discount_active),promo_price=VALUES(promo_price),discount_until=VALUES(discount_until),promo_label=VALUES(promo_label),image=VALUES(image)")
                    ->execute([
                        $id, $nameField,
                        col($r, ['category','kategori']),
                        col($r, ['badge','label']),
                        col($r, ['description','deskripsi','desc']),
                        (float)(col($r, ['normal_price','harga','harga_normal']) ?: 0),
                        col($r, ['unit_label','unit','satuan']),
                        to_bool(col($r, ['discount_active','promo_aktif'])),
                        (float)(col($r, ['promo_price','harga_promo']) ?: 0),
                        to_date(col($r, ['discount_until','promo_sampai'])),
                        col($r, ['promo_label','label_promo']),
                        col($r, ['image','gambar','foto']),
                    ]);
                break;

            case 'nearby':
                $pdo->prepare("INSERT INTO nearby_locations(id,icon,name,dist,time_label,description,sort_order) VALUES(?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE icon=VALUES(icon),name=VALUES(name),dist=VALUES(dist),time_label=VALUES(time_label),description=VALUES(description)")
                    ->execute([
                        col($r, ['id']) ?: ('LOC'.($count+1)),
                        col($r, ['icon','ikon']),
                        col($r, ['name','nama']),
                        col($r, ['dist','jarak']),
                        col($r, ['time','time_label','waktu']),
                        col($r, ['description','deskripsi','desc']),
                        $count,
                    ]);
                break;

            case 'radio':
                $pdo->prepare("INSERT INTO radio_stations(id,name,genre,url,sort_order) VALUES(?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE name=VALUES(name),genre=VALUES(genre),url=VALUES(url)")
                    ->execute([
                        col($r, ['id']) ?: ('RAD'.($count+1)),
                        col($r, ['name','nama']),
                        col($r, ['genre']),
                        col($r, ['url','stream']),
                        $count,
                    ]);
                break;

            case 'invite':
                $pdo->prepare("INSERT INTO invite_codes(code,room_id,room_name,nickname,used) VALUES(?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE room_id=VALUES(room_id),room_name=VALUES(room_name),nickname=VALUES(nickname),used=VALUES(used)")
                    ->execute([
                        col($r, ['code','kode']),
                        col($r, ['room_id','kamar','id_kamar']),
                        col($r, ['room_name','nama_kamar']),
                        col($r, ['nickname','nick']),
                        to_bool(col($r, ['used','terpakai'])),
                    ]);
                break;

            default:
                throw new RuntimeException("Unknown table: $table");
        }
        $count++;
    }

    $pdo->commit();
    echo "Imported $count row(s) into '$table'.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Import failed: ".$e->getMessage()."\n");
    exit(1);
}
