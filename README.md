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

## Deployment Vercel + Postgres (Manajemen Radio Online)

`index.html` adalah versi statis untuk Vercel. Endpoint radio memakai dua jalur:

- `php-api/radio.php` — dipakai saat jalan di XAMPP/MySQL.
- `api/radio.js` — Vercel Function (Node) yang menyimpan data ke Postgres.

Frontend otomatis mencoba `php-api/radio.php` lebih dulu, lalu `api/radio`.

Langkah setup:

1. Buat database Postgres (Neon, Supabase, atau Vercel Postgres), salin connection string.
2. Di Vercel: Project → Settings → Environment Variables, tambahkan `DATABASE_URL`
   (untuk Vercel Postgres, `POSTGRES_URL` juga dikenali otomatis).
3. Redeploy. Tabel `radio_stations` dibuat otomatis pada request pertama dan diisi 4 stasiun awal bila masih kosong.
4. Tambah/hapus/toggle LIVE dari Panel Admin → Manajemen Channel Radio Online langsung tersimpan permanen.

Untuk pengembangan lokal dengan Postgres: `npm install` lalu isi `.env` dari `.env.example`.

Batas yang berlaku: maksimal 5 stasiun, minimal 1 stasiun, URL stream wajib http/https.

### Penyimpanan data Panel Admin

Semua perubahan dari Panel Admin (identitas brand/logo, running text, kamar, promo, menu Sedhah Ayu, moderasi buku tamu, data penghuni, tab navigasi, radar lokasi, channel mIRC) serta registrasi penghuni dan buku tamu publik disimpan otomatis ke tabel `portal_state`:

- `api/state.js` — Vercel Function (Postgres, kolom `JSONB`).
- `php-api/state.php` — padanan untuk XAMPP/MySQL (kolom `LONGTEXT`).

Tabel `portal_state` dibuat otomatis pada request pertama. Simpan berjalan otomatis sekitar 0,7 detik setelah aksi terakhir, dan data dimuat lagi saat halaman dibuka. Stasiun radio tetap memakai tabel `radio_stations` sendiri.

Catatan: payload state dibatasi 4 MB. Foto WebP (logo, KTP, promo) ikut tersimpan di state, jadi hindari mengunggah banyak foto beresolusi besar sekaligus.
