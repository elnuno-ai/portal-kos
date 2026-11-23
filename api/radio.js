const { Pool } = require('pg');

const MAX_STATIONS = 10;

const DEFAULT_STATIONS = [
  { id: 'RAD1', name: 'Groove Salad • Lo-Fi & Chill Kos', genre: 'Ambient / Belajar', url: 'https://ice1.somafm.com/groovesalad-128-mp3' },
  { id: 'RAD2', name: 'Lush • Santai Sore & Gamelan Chill', genre: 'Chillout', url: 'https://ice1.somafm.com/lush-128-mp3' },
  { id: 'RAD3', name: 'Illinois Street Lounge • Nuansa Jadoel', genre: 'Retro Lounge', url: 'https://ice1.somafm.com/illstreet-128-mp3' },
  { id: 'RAD4', name: 'DEF CON Radio • Fokus Coding & Tugas', genre: 'Electronic / Focus', url: 'https://ice1.somafm.com/defcon-128-mp3' },
  { id: 'RAD5', name: 'Prambors FM Jakarta • Pop Indonesia', genre: 'Pop Indonesia', url: 'https://stream.rcs.revma.com/h77wwp48kxcwv' },
  { id: 'RAD6', name: 'Delta FM Jakarta • Hits Santai', genre: 'Pop / Hits', url: 'https://stream.rcs.revma.com/k02rmq48kxcwv' },
  { id: 'RAD7', name: 'RRI Pro 3 KBRN • Berita & Musik', genre: 'Berita / Nasional', url: 'https://stream-node0.rri.co.id/streaming/14/9014/kbrn.mp3' },
  { id: 'RAD8', name: 'SB FM • Tembang Kenangan', genre: 'Tembang Kenangan', url: 'https://stream.zeno.fm/d16n50mkzhhvv' },
];

const LEGACY_PRESET_IDS = ['RAD1', 'RAD2', 'RAD3', 'RAD4'];

let pool = null;
let schemaReady = null;

function getPool() {
  if (pool) return pool;
  const connectionString =
    process.env.DATABASE_URL ||
    process.env.POSTGRES_URL ||
    process.env.POSTGRES_URL_NON_POOLING;
  if (!connectionString) {
    throw new Error('DATABASE_URL / POSTGRES_URL belum diset di environment Vercel.');
  }
  const isLocal = /@(localhost|127\.0\.0\.1)[:/]/.test(connectionString);
  pool = new Pool({
    connectionString,
    ssl: isLocal ? false : { rejectUnauthorized: false },
    max: 3,
  });
  return pool;
}

async function ensureSchema(p) {
  if (!schemaReady) {
    schemaReady = (async () => {
      await p.query(`
        CREATE TABLE IF NOT EXISTS radio_stations (
          id VARCHAR(20) PRIMARY KEY,
          name TEXT NOT NULL,
          genre TEXT,
          url TEXT NOT NULL,
          is_live BOOLEAN NOT NULL DEFAULT FALSE,
          live_started_at TIMESTAMP NULL,
          sort_order INTEGER NOT NULL DEFAULT 0
        )
      `);
      // Dukung tabel lama yang belum punya kolom is_live / live_started_at / sort_order.
      await p.query('ALTER TABLE radio_stations ADD COLUMN IF NOT EXISTS is_live BOOLEAN NOT NULL DEFAULT FALSE');
      await p.query('ALTER TABLE radio_stations ADD COLUMN IF NOT EXISTS live_started_at TIMESTAMP NULL');
      await p.query('ALTER TABLE radio_stations ADD COLUMN IF NOT EXISTS sort_order INTEGER NOT NULL DEFAULT 0');

      const { rows } = await p.query('SELECT COUNT(*)::int AS count FROM radio_stations');
      const count = rows[0].count;

      let shouldSeed = count === 0;
      if (!shouldSeed && count === LEGACY_PRESET_IDS.length) {
        const { rows: existing } = await p.query('SELECT id FROM radio_stations');
        shouldSeed = existing.length === LEGACY_PRESET_IDS.length &&
          existing.every((row) => LEGACY_PRESET_IDS.includes(row.id));
      }

      if (shouldSeed) {
        for (let i = 0; i < DEFAULT_STATIONS.length; i++) {
          const s = DEFAULT_STATIONS[i];
          await p.query(
            'INSERT INTO radio_stations(id,name,genre,url,is_live,sort_order) VALUES ($1,$2,$3,$4,FALSE,$5) ON CONFLICT (id) DO NOTHING',
            [s.id, s.name, s.genre, s.url, i]
          );
        }
      }
    })().catch((err) => {
      schemaReady = null;
      throw err;
    });
  }
  return schemaReady;
}

async function allStations(p) {
  const { rows } = await p.query(
    'SELECT id,name,genre,url,is_live,live_started_at FROM radio_stations ORDER BY sort_order, id'
  );
  return rows.map((r) => ({
    id: r.id,
    name: r.name,
    genre: r.genre,
    url: r.url,
    isLive: r.is_live === true || r.is_live === 1 || r.is_live === '1' || r.is_live === 'true',
    liveStartedAt: r.live_started_at,
  }));
}

function readBody(req) {
  if (req.body && typeof req.body === 'object') return req.body;
  if (typeof req.body === 'string') {
    try { return JSON.parse(req.body); } catch (e) { return {}; }
  }
  return {};
}

function isValidStreamUrl(value) {
  try {
    const u = new URL(value);
    return u.protocol === 'http:' || u.protocol === 'https:';
  } catch (e) {
    return false;
  }
}

// Cek apakah URL mengarah ke halaman web (HTML) dan bukan direct stream audio.
async function inspectStreamUrl(url) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 7000);
  try {
    const res = await fetch(url, {
      method: 'GET',
      redirect: 'follow',
      headers: { 'User-Agent': 'PortalKosRadio/1.0', Range: 'bytes=0-2047' },
      signal: controller.signal,
    });
    const type = (res.headers.get('content-type') || '').toLowerCase();

    if (type.includes('text/html') || type.includes('application/xhtml+xml')) {
      try { if (res.body) await res.body.cancel(); } catch (e) { /* abaikan */ }
      return { isHtml: true };
    }

    const ambiguous = !type || type.includes('application/octet-stream') || type.includes('text/plain') || type.includes('binary');
    if (ambiguous && res.body) {
      const reader = res.body.getReader();
      const { value } = await reader.read();
      try { await reader.cancel(); } catch (e) { /* abaikan */ }
      if (value && value.length) {
        const head = Buffer.from(value.slice(0, 160)).toString('utf8').trim().toLowerCase();
        if (head.startsWith('<!doctype html') || head.startsWith('<html') || head.includes('<head') || head.includes('<body')) {
          return { isHtml: true };
        }
      }
      return { ok: true };
    }

    try { if (res.body) await res.body.cancel(); } catch (e) { /* abaikan */ }
    return { ok: true };
  } catch (e) {
    // Jaringan lambat / diblokir: biarkan elemen audio yang mencoba memutar.
    return { unknown: true };
  } finally {
    clearTimeout(timer);
  }
}

module.exports = async function handler(req, res) {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('Cache-Control', 'no-store');

  try {
    const p = getPool();
    await ensureSchema(p);

    if (req.method !== 'POST') {
      return res.status(200).json({ ok: true, stations: await allStations(p) });
    }

    const input = readBody(req);
    const action = String(input.action || '');

    if (action === 'add') {
      const { rows } = await p.query('SELECT COUNT(*)::int AS count FROM radio_stations');
      if (rows[0].count >= MAX_STATIONS) {
        return res.status(422).json({ ok: false, error: `Maksimal ${MAX_STATIONS} stasiun radio.` });
      }
      const name = String(input.name || '').trim();
      const url = String(input.url || '').trim();
      if (!name || !isValidStreamUrl(url)) {
        return res.status(422).json({ ok: false, error: 'Nama dan URL stream yang valid wajib diisi.' });
      }

      const inspection = await inspectStreamUrl(url);
      if (inspection.isHtml) {
        return res.status(422).json({
          ok: false,
          error: 'URL tersebut adalah halaman web (HTML), bukan direct stream audio. Masukkan URL MP3/AAC/OGG langsung, misalnya https://server-radio/stream.mp3.',
        });
      }

      const genre = String(input.genre || '').trim() || 'Live Stream';
      const isLive = Boolean(input.isLive);
      const id = 'RAD' + Date.now().toString(36).toUpperCase();
      const sort = rows[0].count;
      await p.query(
        'INSERT INTO radio_stations(id,name,genre,url,is_live,live_started_at,sort_order) VALUES ($1,$2,$3,$4,$5,$6,$7)',
        [id, name, genre, url, isLive, isLive ? new Date() : null, sort]
      );
      return res.status(200).json({ ok: true, id, stations: await allStations(p) });
    }

    if (action === 'delete') {
      const { rows } = await p.query('SELECT COUNT(*)::int AS count FROM radio_stations');
      if (rows[0].count <= 1) {
        return res.status(422).json({ ok: false, error: 'Minimal harus ada 1 stasiun.' });
      }
      await p.query('DELETE FROM radio_stations WHERE id=$1', [String(input.id || '')]);
      return res.status(200).json({ ok: true, stations: await allStations(p) });
    }

    if (action === 'toggle_live') {
      const live = Boolean(input.live);
      await p.query('UPDATE radio_stations SET is_live=$1, live_started_at=$2 WHERE id=$3', [
        live,
        live ? new Date() : null,
        String(input.id || ''),
      ]);
      return res.status(200).json({ ok: true, stations: await allStations(p) });
    }

    return res.status(400).json({ ok: false, error: 'Aksi tidak dikenal.' });
  } catch (err) {
    return res.status(500).json({ ok: false, error: err.message || 'Terjadi kesalahan pada server radio.' });
  }
};
