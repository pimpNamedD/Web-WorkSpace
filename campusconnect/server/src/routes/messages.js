import { Router } from 'express';
import { one, query, run } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { asyncHandler, HttpError, threadKey, requireFields } from '../utils/helpers.js';
import { notify } from '../utils/notify.js';

const router = Router();

/**
 * Listing enquiries (1.4.1 "Messaging System"). Deliberately asynchronous -
 * real-time chat is out of scope (1.4.3) - so that students do not have to
 * publish their personal phone numbers.
 */

/** GET /api/messages - conversation list with unread counts. */
router.get('/', requireAuth, asyncHandler(async (req, res) => {
  const threads = await query(
    `SELECT m.thread_key,
            MAX(m.id) AS last_message_id,
            MAX(m.created_at) AS last_at,
            SUM(m.recipient_id = ? AND m.is_read = 0) AS unread
       FROM messages m
      WHERE m.sender_id = ? OR m.recipient_id = ?
      GROUP BY m.thread_key
      ORDER BY last_at DESC`,
    [req.user.id, req.user.id, req.user.id],
  );
  if (!threads.length) return res.json({ threads: [] });

  const ids = threads.map((t) => t.last_message_id);
  const lasts = await query(
    `SELECT m.*, l.title AS listing_title, l.type AS listing_type,
            s.full_name AS sender_name, s.avatar_url AS sender_avatar,
            r.full_name AS recipient_name, r.avatar_url AS recipient_avatar
       FROM messages m
       LEFT JOIN listings l ON l.id = m.listing_id
       JOIN users s ON s.id = m.sender_id
       JOIN users r ON r.id = m.recipient_id
      WHERE m.id IN (${ids.map(() => '?').join(',')})`,
    ids,
  );
  const byId = Object.fromEntries(lasts.map((m) => [m.id, m]));

  res.json({
    threads: threads.map((t) => {
      const last = byId[t.last_message_id];
      const otherIsSender = last.sender_id !== req.user.id;
      return {
        thread_key: t.thread_key,
        unread: Number(t.unread),
        last_at: t.last_at,
        listing_id: last.listing_id,
        listing_title: last.listing_title,
        listing_type: last.listing_type,
        preview: last.body.slice(0, 140),
        counterpart: {
          id: otherIsSender ? last.sender_id : last.recipient_id,
          full_name: otherIsSender ? last.sender_name : last.recipient_name,
          avatar_url: otherIsSender ? last.sender_avatar : last.recipient_avatar,
        },
      };
    }),
  });
}));

/** GET /api/messages/unread-count */
router.get('/unread-count', requireAuth, asyncHandler(async (req, res) => {
  const row = await one('SELECT COUNT(*) AS c FROM messages WHERE recipient_id = ? AND is_read = 0', [req.user.id]);
  res.json({ count: Number(row.c) });
}));

/** GET /api/messages/thread/:key - full conversation; marks it read. */
router.get('/thread/:key', requireAuth, asyncHandler(async (req, res) => {
  const key = req.params.key;
  const parts = key.split(':');
  if (parts.length !== 3 || !parts.slice(1).map(Number).includes(req.user.id)) {
    throw new HttpError(403, 'That conversation does not belong to you.');
  }

  const messages = await query(
    `SELECT m.*, s.full_name AS sender_name, s.avatar_url AS sender_avatar
       FROM messages m JOIN users s ON s.id = m.sender_id
      WHERE m.thread_key = ? ORDER BY m.created_at ASC`,
    [key],
  );
  await run('UPDATE messages SET is_read = 1 WHERE thread_key = ? AND recipient_id = ?', [key, req.user.id]);

  const otherId = Number(parts[1]) === req.user.id ? Number(parts[2]) : Number(parts[1]);
  const counterpart = await one(
    'SELECT id, full_name, avatar_url, verification_status, university FROM users WHERE id = ?',
    [otherId],
  );
  const listingId = Number(parts[0]);
  const listing = listingId
    ? await one('SELECT id, title, type, price, price_unit, status FROM listings WHERE id = ?', [listingId])
    : null;

  res.json({ thread_key: key, messages, counterpart, listing });
}));

/** POST /api/messages - send an enquiry or a reply. */
router.post('/', requireAuth, asyncHandler(async (req, res) => {
  requireFields(req.body, ['recipient_id', 'body']);
  const { recipient_id, listing_id = null, body } = req.body;

  if (Number(recipient_id) === req.user.id) throw new HttpError(400, 'You cannot message yourself.');
  if (String(body).trim().length === 0) throw new HttpError(400, 'Write a message first.');

  const recipient = await one('SELECT id, full_name, is_suspended FROM users WHERE id = ?', [recipient_id]);
  if (!recipient) throw new HttpError(404, 'That user does not exist.');
  if (recipient.is_suspended) throw new HttpError(400, 'That account is suspended and cannot receive messages.');

  let listing = null;
  if (listing_id) {
    listing = await one('SELECT id, title FROM listings WHERE id = ?', [listing_id]);
    if (!listing) throw new HttpError(404, 'That listing no longer exists.');
  }

  const key = threadKey(listing_id ?? 0, req.user.id, recipient_id);
  const result = await run(
    'INSERT INTO messages (thread_key, listing_id, sender_id, recipient_id, body) VALUES (?, ?, ?, ?, ?)',
    [key, listing_id ?? null, req.user.id, recipient_id, String(body).trim()],
  );

  await notify(recipient_id, {
    type: 'message',
    title: `New message from ${req.user.full_name}`,
    body: listing ? `About "${listing.title}": ${String(body).slice(0, 140)}` : String(body).slice(0, 160),
    link: `/dashboard/messages/${key}`,
  });

  res.status(201).json({
    message: await one('SELECT * FROM messages WHERE id = ?', [result.insertId]),
    thread_key: key,
  });
}));

export default router;
