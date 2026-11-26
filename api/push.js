const { Pool } = require('pg');

let pool = null;
let schemaReady = null;
let webpushModule = null;
let webpushError = null;

function getWebPush() {
  if (webpushModule || webpushError) return webpushModule;
  try {
    webpushModule = require('web-push');
  } catch (err) {
    webpushError = err;
    console.error('[push] modul web-push gagal dimuat:', err.message);
  }
  return webpushModule;
}

const VAPID_PUBLIC_KEY = (process.env.VAPID_PUBLIC_KEY || process.env.PUSH_VAPID_PUBLIC_KEY || '').trim();
const VAPID_PRIVATE_KEY = (process.env.VAPID_PRIVATE_KEY || process.env.PUSH_VAPID_PRIVATE_KEY || '').trim();
const VAPID_SUBJECT = (process.env.VAPID_SUBJECT || 'mailto:admin@portal-kos.local').trim();
const ADMIN_TOKEN = (process.env.PUSH_ADMIN_TOKEN || '').trim();

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
      CREATE TABLE IF NOT EXISTS push_subscriptions (
        id SERIAL PRIMARY KEY,
        endpoint TEXT UNIQUE NOT NULL,
        p256dh TEXT NOT NULL,
        auth TEXT NOT NULL,
        audience TEXT NOT NULL DEFAULT 'visitor',
        username TEXT,
        label TEXT,
        created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
        updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
      )
    `).catch((err) => {
      schemaReady = null;
      throw err;
    });
  }
  return schemaReady;
}

function isVapidReady() {
  return !!(VAPID_PUBLIC_KEY && VAPID_PRIVATE_KEY);
}

function readBody(req) {
  if (req.body && typeof req.body === 'object' && !Buffer.isBuffer(req.body)) return req.body;
  const raw = Buffer.isBuffer(req.body) ? req.body.toString('utf8') : req.body;
  if (typeof raw === 'string') {
    try { return JSON.parse(raw); } catch (err) { return null; }
  }
  return null;
}

function cleanSubscription(raw) {
  if (!raw || typeof raw !== 'object') return null;
  const endpoint = String(raw.endpoint || '').trim();
  const keys = raw.keys || {};
  const p256dh = String(keys.p256dh || '').trim();
  const auth = String(keys.auth || '').trim();
  if (!/^https:\/\//.test(endpoint) || !p256dh || !auth) return null;
  return { endpoint, p256dh, auth };
}

function payloadFor(body) {
  const title = String(body.title || 'Portal Kos Sedhah Ayu').slice(0, 120);
  const text = String(body.body || '').slice(0, 300);
  if (!text) return null;
  return {
    title,
    body: text,
    url: String(body.url || '/').slice(0, 500),
    tag: String(body.tag || 'portal-kos').slice(0, 64),
    requireInteraction: body.priority === 'urgent',
  };
}

module.exports = async function handler(req, res) {
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('Cache-Control', 'no-store');
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Push-Token');

  if (req.method === 'OPTIONS') return res.status(200).end();
  if (req.method !== 'GET' && req.method !== 'POST') {
    res.setHeader('Allow', 'GET, POST, OPTIONS');
    return res.status(405).json({ ok: false, error: 'Metode tidak diizinkan.' });
  }

  try {
    const p = getPool();
    await ensureSchema(p);

    if (req.method === 'GET') {
      let action = req.query && req.query.action;
      if (!action && req.url) {
        try { action = new URL(req.url, 'http://localhost').searchParams.get('action'); } catch (err) { /* abaikan */ }
      }
      action = String(action || 'config');
      if (action === 'stats') {
        const { rows } = await p.query(`
          SELECT audience, COUNT(*)::int AS total
          FROM push_subscriptions
          GROUP BY audience
        `);
        const counts = { visitor: 0, member: 0 };
        rows.forEach(row => { counts[row.audience] = row.total; });
        const latest = await p.query('SELECT MAX(updated_at) AS last_at FROM push_subscriptions');
        return res.status(200).json({
          ok: true,
          enabled: isVapidReady(),
          total: counts.visitor + counts.member,
          visitor: counts.visitor,
          member: counts.member,
          lastSubscribedAt: latest.rows[0] ? latest.rows[0].last_at : null,
        });
      }
      return res.status(200).json({
        ok: true,
        enabled: isVapidReady(),
        publicKey: VAPID_PUBLIC_KEY,
      });
    }

    const body = readBody(req);
    if (!body || typeof body !== 'object') {
      return res.status(400).json({ ok: false, error: 'Body JSON tidak valid.' });
    }
    const action = String(body.action || '').trim();

    if (action === 'subscribe') {
      const subscription = cleanSubscription(body.subscription);
      if (!subscription) {
        return res.status(422).json({ ok: false, error: 'Data subscription push tidak valid.' });
      }
      const audience = body.audience === 'member' ? 'member' : 'visitor';
      const username = audience === 'member' ? String(body.username || '').slice(0, 80) : null;
      const label = String(body.label || '').slice(0, 160);
      await p.query(
        `
        INSERT INTO push_subscriptions (endpoint, p256dh, auth, audience, username, label)
        VALUES ($1, $2, $3, $4, $5, $6)
        ON CONFLICT (endpoint) DO UPDATE SET
          p256dh = EXCLUDED.p256dh,
          auth = EXCLUDED.auth,
          audience = EXCLUDED.audience,
          username = EXCLUDED.username,
          label = EXCLUDED.label,
          updated_at = now()
        `,
        [subscription.endpoint, subscription.p256dh, subscription.auth, audience, username, label]
      );
      return res.status(200).json({ ok: true, audience });
    }

    if (action === 'unsubscribe') {
      const endpoint = String(body.endpoint || '').trim();
      if (!endpoint) return res.status(422).json({ ok: false, error: 'Endpoint kosong.' });
      await p.query('DELETE FROM push_subscriptions WHERE endpoint = $1', [endpoint]);
      return res.status(200).json({ ok: true });
    }

    if (action === 'cleanup') {
      const result = await p.query(`
        DELETE FROM push_subscriptions
        WHERE endpoint = '' OR p256dh = '' OR auth = ''
      `);
      return res.status(200).json({ ok: true, removed: result.rowCount || 0 });
    }

    if (action === 'send') {
      if (!ADMIN_TOKEN) {
        return res.status(503).json({
          ok: false,
          error: 'PUSH_ADMIN_TOKEN belum diset di environment Vercel.',
        });
      }
      const token = String(body.token || req.headers['x-push-token'] || '').trim();
      if (token !== ADMIN_TOKEN) {
        return res.status(403).json({ ok: false, error: 'Kunci admin push salah.' });
      }
      if (!isVapidReady()) {
        return res.status(503).json({
          ok: false,
          error: 'VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY belum diset di environment Vercel.',
        });
      }
      const webpush = getWebPush();
      if (!webpush) {
        return res.status(503).json({
          ok: false,
          error: 'Modul web-push tidak tersedia di deployment. Jalankan ulang build dengan npm install.',
        });
      }
      const payload = payloadFor(body);
      if (!payload) {
        return res.status(422).json({ ok: false, error: 'Judul/isi notifikasi belum lengkap.' });
      }

      webpush.setVapidDetails(VAPID_SUBJECT, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY);
      const audience = String(body.audience || 'all');
      const params = [];
      let where = '';
      if (audience === 'visitor' || audience === 'member') {
        params.push(audience);
        where = 'WHERE audience = $1';
      }
      const { rows } = await p.query(
        `SELECT id, endpoint, p256dh, auth FROM push_subscriptions ${where} ORDER BY id DESC LIMIT 2000`,
        params
      );
      if (!rows.length) {
        return res.status(200).json({ ok: true, total: 0, sent: 0, failed: 0, removed: 0 });
      }

      let sent = 0;
      let failed = 0;
      const invalidIds = [];
      const chunkSize = 20;
      for (let i = 0; i < rows.length; i += chunkSize) {
        const chunk = rows.slice(i, i + chunkSize);
        const results = await Promise.allSettled(chunk.map(row =>
          webpush.sendNotification(
            { endpoint: row.endpoint, keys: { p256dh: row.p256dh, auth: row.auth } },
            JSON.stringify(payload),
            { TTL: 86400, headers: payload.requireInteraction ? { Urgency: 'high' } : {} }
          )
        ));
        results.forEach((result, index) => {
          if (result.status === 'fulfilled') {
            sent += 1;
            return;
          }
          failed += 1;
          const statusCode = result.reason && result.reason.statusCode;
          if (statusCode === 404 || statusCode === 410) invalidIds.push(chunk[index].id);
        });
      }

      if (invalidIds.length) {
        await p.query('DELETE FROM push_subscriptions WHERE id = ANY($1::int[])', [invalidIds]);
      }
      return res.status(200).json({
        ok: true,
        total: rows.length,
        sent,
        failed,
        removed: invalidIds.length,
      });
    }

    return res.status(422).json({ ok: false, error: 'Aksi push tidak dikenal.' });
  } catch (err) {
    return res.status(500).json({ ok: false, error: err.message });
  }
};
