# Ekosistem Hub — Kos & Sedhah Ayu (PHP + MySQL)

## Setup
1. Start Apache + MySQL dari XAMPP.
2. Import schema: `mysql -u root < sql/schema.sql`
3. Isi data awal dari `config/portal_data.php`: `php sql/seed.php`
4. Buka `http://localhost/project%20kos2an/`

Login admin (users): `admin` / `admin123`.

## Impor dari Google Sheets / CSV
Share Sheet sebagai **Anyone with the link (Viewer)**, lalu jalankan:

```bat
php lib/import.php tenants  data/tenants.csv
php lib/import.php tenants  "https://docs.google.com/spreadsheets/d/<ID>/edit#gid=0"
php lib/import.php rooms    data/rooms.csv
php lib/import.php guestbook data/guestbook.csv
php lib/import.php promos   data/promos.csv
php lib/import.php menu     data/menu.csv
php lib/import.php nearby   data/nearby.csv
php lib/import.php radio    data/radio.csv
php lib/import.php invite   data/invite.csv
```

Header CSV fleksibel (Indonesia/English), contoh untuk tenants:
`username, nama_lengkap, kamar, no_hp, pekerjaan, tanggal_masuk, status_bayar, nominal, jatuh_tempo`

Catatan keamanan: gunakan service account / link-sharing read-only, jangan simpan kredensial di dalam webroot.
