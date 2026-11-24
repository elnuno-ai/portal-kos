const { Pool } = require('pg');

// Batas aman di bawah limit platform Vercel (4,5 MB per request).
const MAX_BODY_BYTES = Number(process.env.MAX_STATE_BYTES || 4 * 1024 * 1024);

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
  if (req.body && typeof req.body === 'object' && !Buffer.isBuffer(req.body)) return req.body;
  const raw = Buffer.isBuffer(req.body) ? req.body.toString('utf8') : req.body;
  if (typeof raw === 'string') {
    try { return JSON.parse(raw); } catch (e) { return null; }
  }
  return null;
}

function rawBodyBytes(req) {
  if (typeof req.body === 'string') return Buffer.byteLength(req.body);
  if (Buffer.isBuffer(req.body)) return req.body.length;
  return 0;
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

    const rawBytes = rawBodyBytes(req);
    if (rawBytes > MAX_BODY_BYTES) {
      return res.status(413).json({
        ok: false,
        error: `Payload terlalu besar (${(rawBytes / 1024 / 1024).toFixed(2)} MB). Maksimal ${(MAX_BODY_BYTES / 1024 / 1024).toFixed(0)} MB.`,
      });
    }

    const body = readBody(req);
    if (!body) {
      return res.status(400).json({
        ok: false,
        error: 'Body JSON tidak valid/tidak ditemukan (kemungkinan request terlalu besar atau Content-Type salah).',
      });
    }

    const state = body.state;
    if (!state || typeof state !== 'object' || Array.isArray(state)) {
      return res.status(422).json({ ok: false, error: 'Payload state tidak valid.' });
    }

    const json = JSON.stringify(state);
    const jsonBytes = Buffer.byteLength(json);
    if (jsonBytes > MAX_BODY_BYTES) {
      return res.status(413).json({
        ok: false,
        error: `Data state terlalu besar (${(jsonBytes / 1024 / 1024).toFixed(2)} MB). Maksimal ${(MAX_BODY_BYTES / 1024 / 1024).toFixed(0)} MB. Kecilkan foto/gambar.`,
      });
    }

    await p.query(
      `INSERT INTO portal_state(id, data, updated_at) VALUES (1, $1::jsonb, now())
       ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, updated_at = now()`,
      [json]
    );
    return res.status(200).json({ ok: true, bytes: jsonBytes });
  } catch (err) {
    const detail = (err && (err.message || String(err))) || 'Gagal menyimpan state.';
    console.error('[state] save failed:', detail, err && err.code ? `(code ${err.code})` : '', err && err.stack);
    return res.status(500).json({
      ok: false,
      error: detail,
      code: err && err.code ? err.code : undefined,
    });
  }
};
