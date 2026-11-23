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

module.exports = async function handler(req, res) {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('Cache-Control', 'no-store');

  try {
    const p = getPool();
    await p.query(`
      CREATE TABLE IF NOT EXISTS portal_state (
        id INTEGER PRIMARY KEY DEFAULT 1,
        data JSONB NOT NULL,
        updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
      )
    `);
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
