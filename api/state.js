const { Pool } = require('pg');

const MAX_BODY_BYTES = 4 * 1024 * 1024;

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
    schemaReady = p.query(`
      CREATE TABLE IF NOT EXISTS portal_state (
        id INTEGER PRIMARY KEY DEFAULT 1,
        data JSONB NOT NULL,
        updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
      )
    `).catch((err) => {
      schemaReady = null;
      throw err;
    });
  }
  return schemaReady;
}

function readBody(req) {
  if (req.body && typeof req.body === 'object') return req.body;
  if (typeof req.body === 'string') {
    try { return JSON.parse(req.body); } catch (e) { return {}; }
  }
  return {};
}

module.exports = async function handler(req, res) {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('Cache-Control', 'no-store');

  try {
    const p = getPool();
    await ensureSchema(p);

    if (req.method !== 'POST') {
      const { rows } = await p.query('SELECT data FROM portal_state WHERE id=1');
      return res.status(200).json({ ok: true, state: rows.length ? rows[0].data : null });
    }

    const body = readBody(req);
    const state = body.state;
    if (!state || typeof state !== 'object' || Array.isArray(state)) {
      return res.status(422).json({ ok: false, error: 'Payload state tidak valid.' });
    }

    const json = JSON.stringify(state);
    if (Buffer.byteLength(json) > MAX_BODY_BYTES) {
      return res.status(413).json({ ok: false, error: 'Data terlalu besar (maksimal 4 MB).' });
    }

    await p.query(
      `INSERT INTO portal_state(id, data, updated_at) VALUES (1, $1::jsonb, now())
       ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, updated_at = now()`,
      [json]
    );
    return res.status(200).json({ ok: true });
  } catch (err) {
    return res.status(500).json({ ok: false, error: err.message });
  }
};
