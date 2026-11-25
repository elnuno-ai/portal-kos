const { Pool } = require('pg');

let pool = null;

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
    max: 2,
  });
  return pool;
}

function sanitizeMessage(raw) {
  if (!raw || typeof raw !== 'object') return null;
  const type = ['chat', 'system', 'action'].includes(raw.type) ? raw.type : 'chat';
  const message = {
    type,
    time: String(raw.time || '').slice(0, 20),
    ts: Number.isFinite(Number(raw.ts)) ? Number(raw.ts) : Date.now(),
    text: String(raw.text || '').slice(0, 2000),
  };
  if (raw.nick) message.nick = String(raw.nick).slice(0, 40);
  if (raw.role) message.role = raw.role === 'admin' ? 'admin' : 'tenant';
  if (raw.to) message.to = String(raw.to).slice(0, 40);
  return message.text ? message : null;
}

module.exports = async function handler(req, res) {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('Cache-Control', 'no-store');
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Accept');

  if (req.method === 'OPTIONS') return res.status(200).end();
  if (req.method !== 'GET' && req.method !== 'POST') {
    res.setHeader('Allow', 'GET, POST, OPTIONS');
    return res.status(405).json({ ok: false, error: 'Metode tidak diizinkan.' });
  }

  try {
    const p = getPool();
    await p.query(`
      CREATE TABLE IF NOT EXISTS portal_state (
        id INTEGER PRIMARY KEY DEFAULT 1,
        data JSONB NOT NULL,
        updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
      )
    `);
    await p.query(`
      INSERT INTO portal_state (id, data) VALUES (1, '{}'::jsonb)
      ON CONFLICT (id) DO NOTHING
    `);

    if (req.method === 'POST') {
      const body = typeof req.body === 'string' ? JSON.parse(req.body || '{}') : (req.body || {});
      const channel = String(body.channel || '').trim();
      const message = sanitizeMessage(body.message);
      if (!/^[#+@][\w\-.]{1,60}$/.test(channel)) {
        return res.status(400).json({ ok: false, error: 'Channel mIRC tidak valid.' });
      }
      if (!message) {
        return res.status(400).json({ ok: false, error: 'Isi pesan kosong atau tidak valid.' });
      }

      await p.query(
        `
        UPDATE portal_state
        SET data = jsonb_set(
          jsonb_set(
            jsonb_set(
              COALESCE(data, '{}'::jsonb),
              ARRAY['mircChannels'],
              COALESCE(data->'mircChannels', '{}'::jsonb),
              true
            ),
            ARRAY['mircChannels', $1::text],
            COALESCE(data->'mircChannels'->$1::text, '{}'::jsonb),
            true
          ),
          ARRAY['mircChannels', $1::text, 'messages'],
          COALESCE(data->'mircChannels'->$1::text->'messages', '[]'::jsonb) || $2::jsonb,
          true
        ),
        updated_at = now()
        WHERE id = 1
        `,
        [channel, JSON.stringify([message])]
      );

      return res.status(200).json({ ok: true, channel, message });
    }

    const { rows } = await p.query(`
      SELECT data->'mircChannels' AS channels, data->'registeredTenants' AS tenants
      FROM portal_state WHERE id=1
    `);
    const row = rows[0] || {};
    const memberUsernames = Array.isArray(row.tenants)
      ? row.tenants.map(tenant => tenant && tenant.username).filter(Boolean)
      : null;
    return res.status(200).json({
      ok: true,
      mircChannels: row.channels || null,
      memberUsernames,
    });
  } catch (err) {
    return res.status(500).json({ ok: false, error: err.message });
  }
};
