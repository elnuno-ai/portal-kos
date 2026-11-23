<?php
// Initial portal data. Replace with database queries when upgrading to MySQL.
return [
    'siteBrandConfig' => [
        'logoText' => 'IK',
        'logoImage' => null,
        'title' => 'Ekosistem Hub • Kos & Sedhah Ayu',
        'subtitle' => 'Inu Kerta • Secang, Kab. Magelang',
        'footerLeft' => 'Ekosistem Hub • Kos Inu Kerta & Pondhok Dhahar "Sedhah Ayu" — Secang, Kab. Magelang.',
        'footerRight' => 'MikroTik RB750Gr3 Ready • WebP Compressed • PWA + mIRC32 Live Sync',
    ],
    'tickerConfig' => [
        'text' => 'Selamat datang di Ekosistem Hub Inu Kerta Secang! Tersedia kamar kos siap huni dengan Wi-Fi MikroTik stabil & paket makan siang hemat di Pondhok Dhahar "Sedhah Ayu".',
        'speed' => '22s',
        'color' => 'amber',
        'active' => true,
    ],
    'tabConfigs' => [
        ['key' => 'about', 'navLabel' => 'About Us', 'sectionTitle' => 'Ekosistem Hunian Nyaman, Kuliner Jadoel & Solusi Digital Lokal.', 'sectionDesc' => 'Dikelola oleh Inu Kerta, platform satu pintu ini menyatukan layanan hunian kos modern berbasis jaringan MikroTik terkelola, warung makan tradisional Pondhok Dhahar "Sedhah Ayu", serta pendampingan Digital Marketing & AI Workflow untuk UMKM Magelang.', 'isSystem' => true],
        ['key' => 'kos', 'navLabel' => 'Info Kos', 'sectionTitle' => 'Katalog & Ketersediaan Kamar Kos', 'sectionDesc' => 'Jaringan MikroTik RB750Gr3 Hotspot (Batas 2 perangkat aktif/kamar).', 'isSystem' => true],
        ['key' => 'sedhah', 'navLabel' => 'Sedhah Ayu', 'sectionTitle' => 'Pondhok Dhahar "Sedhah Ayu"', 'sectionDesc' => 'Cita rasa masakan tradisional Jawa rumahan hangat setiap hari pukul 06.30 – 20.30 WIB. Khusus penghuni kos tersedia layanan titip antar langsung lewat grup obrolan!', 'isSystem' => true],
        ['key' => 'promo', 'navLabel' => 'Promo & Titip Jual', 'sectionTitle' => 'Promo & Etalase Titip Jual UMKM', 'sectionDesc' => 'Penawaran kamar kos, menu harian Sedhah Ayu, serta produk titip jual warga sekitar (Format visual IG 1:1).', 'isSystem' => true],
        ['key' => 'tamu', 'navLabel' => 'Buku Tamu', 'sectionTitle' => 'Isi Buku Tamu', 'sectionDesc' => 'Foto otomatis dikompres ke format .WebP ringan dan tampil setelah diverifikasi Admin.', 'isSystem' => true],
        ['key' => 'radar', 'navLabel' => 'Radar Lokasi', 'sectionTitle' => 'Radar Lokasi Sekitar Kos (Secang, Magelang)', 'sectionDesc' => 'Fasilitas penting di sekitar kos lengkap dengan jarak dan kontak cepat.', 'isSystem' => true, 'isSubSection' => true],
    ],
    'rooms' => [
        ['id' => 'K01', 'name' => 'Kamar 01', 'floor' => 'Lantai 1 • 3x3m', 'normalPrice' => 650000, 'discountActive' => false, 'promoPrice' => 600000, 'discountUntil' => '', 'promoLabel' => '', 'status' => 'occupied', 'tenant' => 'Dimas Pratama', 'desc' => 'Kamar bersih dekat area parkir utama, sirkulasi udara lancar.', 'facilities' => ['Kasur Springbed', 'Lemari', 'Wi-Fi MikroTik (2 Device)']],
        ['id' => 'K02', 'name' => 'Kamar 02', 'floor' => 'Lantai 1 • 3x3.5m', 'normalPrice' => 750000, 'discountActive' => true, 'promoPrice' => 650000, 'discountUntil' => '2026-12-31', 'promoLabel' => 'Promo Awal Bulan', 'status' => 'available', 'tenant' => null, 'desc' => 'Kamar luas menghadap taman depan, suasana tenang dan sejuk.', 'facilities' => ['Kasur Springbed', 'Meja Belajar', 'Lemari', 'Wi-Fi MikroTik (2 Device)']],
        ['id' => 'K03', 'name' => 'Kamar 03', 'floor' => 'Lantai 1 • 3x3m', 'normalPrice' => 650000, 'discountActive' => false, 'promoPrice' => 600000, 'discountUntil' => '', 'promoLabel' => '', 'status' => 'occupied', 'tenant' => 'Rina Marlina', 'desc' => 'Dekat akses dapur bersama dan jemuran tertutup.', 'facilities' => ['Kasur', 'Lemari', 'Wi-Fi MikroTik (2 Device)']],
        ['id' => 'K04', 'name' => 'Kamar 04', 'floor' => 'Lantai 2 • 3x3m', 'normalPrice' => 650000, 'discountActive' => false, 'promoPrice' => 600000, 'discountUntil' => '', 'promoLabel' => '', 'status' => 'occupied', 'tenant' => 'Fajar Nugroho', 'desc' => 'Lantai 2 dengan pencahayaan alami yang terang.', 'facilities' => ['Kasur', 'Lemari', 'Wi-Fi MikroTik (2 Device)']],
        ['id' => 'K05', 'name' => 'Kamar 05', 'floor' => 'Lantai 2 • 3x3.5m', 'normalPrice' => 750000, 'discountActive' => true, 'promoPrice' => 675000, 'discountUntil' => '2026-12-25', 'promoLabel' => 'Diskon Mahasiswa', 'status' => 'available', 'tenant' => null, 'desc' => 'Kamar sudut lantai 2 dengan jendela besar dan angin sepoi.', 'facilities' => ['Kasur Springbed', 'Meja Belajar', 'Lemari', 'Wi-Fi MikroTik (2 Device)']],
        ['id' => 'K06', 'name' => 'Kamar 06', 'floor' => 'Lantai 2 • 3x3m', 'normalPrice' => 650000, 'discountActive' => false, 'promoPrice' => 600000, 'discountUntil' => '', 'promoLabel' => '', 'status' => 'available', 'tenant' => null, 'desc' => 'Kamar rapi siap huni, colokan listrik ganda.', 'facilities' => ['Kasur', 'Lemari', 'Wi-Fi MikroTik (2 Device)']],
        ['id' => 'K07', 'name' => 'Kamar 07', 'floor' => 'Lantai 2 • 3x3m', 'normalPrice' => 650000, 'discountActive' => false, 'promoPrice' => 600000, 'discountUntil' => '', 'promoLabel' => '', 'status' => 'available', 'tenant' => null, 'desc' => 'Tenang di bagian belakang lantai 2, cocok untuk fokus kerja/kuliah.', 'facilities' => ['Kasur', 'Lemari', 'Wi-Fi MikroTik (2 Device)']],
    ],
    'nearbyLocations' => [
        ['id' => 'LOC1', 'icon' => '🍛', 'name' => 'Pondhok Dhahar "Sedhah Ayu"', 'dist' => '30 meter', 'time' => '1 menit jalan', 'desc' => 'Warung makan jadoel & catering rumahan (06.30–20.30 WIB).'],
        ['id' => 'LOC2', 'icon' => '🧺', 'name' => 'Laundry Kiloan Berkah Secang', 'dist' => '120 meter', 'time' => '2 menit jalan', 'desc' => 'Cuci kering setrika kilat 1 hari jadi, bisa antar-jemput kos.'],
        ['id' => 'LOC3', 'icon' => '💧', 'name' => 'Depot Air Galon & Gas Tirta', 'dist' => '150 meter', 'time' => '2 menit jalan', 'desc' => 'Isi ulang RO/Mineral & tukar galon Aqua, siap antar ke kamar.'],
        ['id' => 'LOC4', 'icon' => '🖨️', 'name' => 'Fotokopi & ATK Prima Kampus', 'dist' => '250 meter', 'time' => '3 menit jalan', 'desc' => 'Print warna, jilid skripsi, dan alat tulis kantor.'],
        ['id' => 'LOC5', 'icon' => '🏪', 'name' => 'Minimarket & ATM Center Secang', 'dist' => '400 meter', 'time' => '2 menit motor', 'desc' => 'Tarik tunai, top-up e-wallet, dan kebutuhan harian 24 jam.'],
        ['id' => 'LOC6', 'icon' => '🏢', 'name' => 'Kantor Pengelola & Pos Kamling', 'dist' => '15 meter', 'time' => 'Area Depan Kos', 'desc' => 'Pusat layanan pengelola kos Inu Kerta & titik kumpul tamu.'],
    ],
    'inviteCodes' => [
        ['code' => 'KMR02-BIMA', 'roomId' => 'K02', 'roomName' => 'Kamar 02', 'nickname' => 'Kamar02_Bima', 'used' => false],
        ['code' => 'KMR05-SARI', 'roomId' => 'K05', 'roomName' => 'Kamar 05', 'nickname' => 'Kamar05_Sari', 'used' => false],
    ],
    'registeredTenants' => [
        ['username' => 'kamar01', 'pin' => '1234', 'wifiPassword' => 'kos01#882', 'fullName' => 'Dimas Pratama', 'roomId' => 'K01', 'roomName' => 'Kamar 01', 'nickname' => 'Kamar01_Dimas', 'phone' => '081227001101', 'emergencyPhone' => '081328009901 (Ayah)', 'originAddress' => 'Jl. Pemuda No. 45, Muntilan, Magelang', 'profession' => 'Mahasiswa', 'workplace' => 'Universitas Tidar', 'socialHandle' => '@dimas.prt', 'ktpPhoto' => null, 'joinDate' => '01 Okt 2026', 'billingStatus' => 'LUNAS', 'billingAmount' => 'Rp 650.000', 'billingDue' => '10 Des 2026'],
        ['username' => 'kamar03', 'pin' => '1234', 'wifiPassword' => 'kos03#419', 'fullName' => 'Rina Marlina', 'roomId' => 'K03', 'roomName' => 'Kamar 03', 'nickname' => 'Kamar03_Rina', 'phone' => '085743002203', 'emergencyPhone' => '085643008803 (Ibu)', 'originAddress' => 'Desa Kalikajar, Wonosobo', 'profession' => 'Karyawan Swasta', 'workplace' => 'Klinik Pratama Secang', 'socialHandle' => '@rinamarlina', 'ktpPhoto' => null, 'joinDate' => '15 Okt 2026', 'billingStatus' => 'LUNAS', 'billingAmount' => 'Rp 650.000', 'billingDue' => '15 Des 2026'],
        ['username' => 'kamar04', 'pin' => '1234', 'wifiPassword' => 'kos04#735', 'fullName' => 'Fajar Nugroho', 'roomId' => 'K04', 'roomName' => 'Kamar 04', 'nickname' => 'Kamar04_Fajar', 'phone' => '082136004404', 'emergencyPhone' => '081229007704 (Kakak)', 'originAddress' => 'Grabag, Kab. Magelang', 'profession' => 'Wirausaha / Freelancer', 'workplace' => 'Studio Desain Grabag', 'socialHandle' => '@fajarnug', 'ktpPhoto' => null, 'joinDate' => '01 Nov 2026', 'billingStatus' => 'LUNAS', 'billingAmount' => 'Rp 650.000', 'billingDue' => '10 Des 2026'],
    ],
    'alumniArchive' => [],
    'guestbookEntries' => [
        ['id' => 'GB1', 'name' => 'Keluarga Pak Hendra (Semarang)', 'category' => 'Pelanggan Sedhah Ayu', 'message' => 'Mampir makan siang di Sedhah Ayu dalam perjalanan ke Jogja. Sayur lodeh dan ayam goreng lengkuasnya juara, suasana jadoelnya bikin kangen rumah!', 'photo' => null, 'approved' => true, 'date' => '18 Nov 2026'],
        ['id' => 'GB2', 'name' => 'Arif Setiawan (Alumni Kamar 06)', 'category' => 'Tamu / Alumni Penghuni Kos', 'message' => 'Selama 2 tahun ngekos di tempat Mas Inu nyaman banget, Wi-Fi stabil buat ngerjain skripsi sampai lulus.', 'photo' => null, 'approved' => true, 'date' => '12 Nov 2026'],
        ['id' => 'GB3', 'name' => 'Mbak Maya (UMKM Keripik Secang)', 'category' => 'Mitra UMKM / Klien Digital', 'message' => 'Terima kasih sudah dibantu optimasi Google Maps dan etalase titip jual di portal ini!', 'photo' => null, 'approved' => false, 'date' => 'Hari Ini'],
    ],
    'mircChannels' => [
        '#umum' => ['topic' => 'Obrolan santai seluruh penghuni kos Secang. Ketik /help untuk daftar perintah.', 'messages' => []],
        '#pengumuman' => ['topic' => 'Info resmi pengelola kos & promo menu harian Pondhok Dhahar Sedhah Ayu.', 'messages' => []],
        '#titip-beli' => ['topic' => 'Titip beli makan siang/malam di Sedhah Ayu, galon air, atau laundry bareng!', 'messages' => []],
        '#mabar' => ['topic' => 'Ajak mabar MLBB / PUBG / futsal sore anak kos.', 'messages' => []],
    ],
    'radioStations' => [
        ['id' => 'RAD1', 'name' => 'Groove Salad • Lo-Fi & Chill Kos', 'genre' => 'Ambient / Belajar', 'url' => 'https://ice1.somafm.com/groovesalad-128-mp3'],
        ['id' => 'RAD2', 'name' => 'Lush • Santai Sore & Gamelan Chill', 'genre' => 'Chillout', 'url' => 'https://ice1.somafm.com/lush-128-mp3'],
        ['id' => 'RAD3', 'name' => 'Illinois Street Lounge • Nuansa Jadoel', 'genre' => 'Retro Lounge', 'url' => 'https://ice1.somafm.com/illstreet-128-mp3'],
        ['id' => 'RAD4', 'name' => 'DEF CON Radio • Fokus Coding & Tugas', 'genre' => 'Electronic / Focus', 'url' => 'https://ice1.somafm.com/defcon-128-mp3'],
        ['id' => 'RAD5', 'name' => 'Prambors FM Jakarta • Pop Indonesia', 'genre' => 'Pop Indonesia', 'url' => 'https://stream.rcs.revma.com/h77wwp48kxcwv'],
        ['id' => 'RAD6', 'name' => 'Delta FM Jakarta • Hits Santai', 'genre' => 'Pop / Hits', 'url' => 'https://stream.rcs.revma.com/k02rmq48kxcwv'],
        ['id' => 'RAD7', 'name' => 'RRI Pro 3 KBRN • Berita & Musik', 'genre' => 'Berita / Nasional', 'url' => 'https://stream-node0.rri.co.id/streaming/14/9014/kbrn.mp3'],
        ['id' => 'RAD8', 'name' => 'SB FM • Tembang Kenangan', 'genre' => 'Tembang Kenangan', 'url' => 'https://stream.zeno.fm/d16n50mkzhhvv'],
    ],
];
