const { Pool } = require('pg');

const DEFAULT_STATIONS = [
  { id: 'RAD1', name: 'Groove Salad • Lo-Fi & Chill Kos', genre: 'Ambient / Belajar', url: 'https://ice1.somafm.com/groovesalad-128-mp3' },
  { id: 'RAD2', name: 'Lush • Santai Sore & Gamelan Chill', genre: 'Chillout', url: 'https://ice1.somafm.com/lush-128-mp3' },
  { id: 'RAD3', name: 'Illinois Street Lounge • Nuansa Jadoel', genre: 'Retro Lounge', url: 'https://ice1.somafm.com/illstreet-128-mp3' },
  { id: 'RAD4', name: 'DEF CON Radio • Fokus Coding & Tugas', genre: 'Electronic / Focus', url: 'https://ice1.somafm.com/defcon-128-mp3' },
];

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
      const { rows } = await p.query('SELECT COUNT(*)::int AS count FROM radio_stations');
      if (rows[0].count === 0) {
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
    isLive: !!r.is_live,
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
      if (rows[0].count >= 5) {
        return res.status(422).json({ ok: false, error: 'Maksimal 5 stasiun radio.' });
      }
      const name = String(input.name || '').trim();
      const url = String(input.url || '').trim();
      if (!name || !isValidStreamUrl(url)) {
        return res.status(422).json({ ok: false, error: 'Nama dan URL stream yang valid wajib diisi.' });
      }
      const genre = String(input.genre || '').trim() || 'Live Stream';
      const isLive = !!input.isLive;
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
      const live = !!input.live;
      await p.query('UPDATE radio_stations SET is_live=$1, live_started_at=$2 WHERE id=$3', [
        live,
        live ? new Date() : null,
        String(input.id || ''),
      ]);
      return res.status(200).json({ ok: true, stations: await allStations(p) });
    }

    return res.status(400).json({ ok: false, error: 'Aksi tidak dikenal.' });
  } catch (err) {
    return res.status(500).json({ ok: false, error: err.message });
  }
};
