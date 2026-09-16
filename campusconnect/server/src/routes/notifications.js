import { Router } from 'express';
import { one, query, run } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { asyncHandler, toInt } from '../utils/helpers.js';

const router = Router();

/** GET /api/notifications */
router.get('/', requireAuth, asyncHandler(async (req, res) => {
  const limit = toInt(req.query.limit, 30, { min: 1, max: 100 });
  const unreadOnly = ['1', 'true'].includes(String(req.query.unread).toLowerCase());
  const rows = await query(
    `SELECT * FROM notifications WHERE user_id = ?${unreadOnly ? ' AND is_read = 0' : ''}
      ORDER BY created_at DESC LIMIT ${limit}`,
    [req.user.id],
  );
  const unread = await one('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0', [req.user.id]);
  res.json({ notifications: rows, unread_count: Number(unread.c) });
}));

/** GET /api/notifications/unread-count */
router.get('/unread-count', requireAuth, asyncHandler(async (req, res) => {
  const row = await one('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0', [req.user.id]);
  res.json({ count: Number(row.c) });
}));

/** PATCH /api/notifications/:id/read */
router.patch('/:id/read', requireAuth, asyncHandler(async (req, res) => {
  await run('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [req.params.id, req.user.id]);
  res.json({ message: 'Marked as read.' });
}));

/** POST /api/notifications/read-all */
router.post('/read-all', requireAuth, asyncHandler(async (req, res) => {
  const result = await run('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [req.user.id]);
  res.json({ updated: result.affectedRows });
}));

/** DELETE /api/notifications/:id */
router.delete('/:id', requireAuth, asyncHandler(async (req, res) => {
  await run('DELETE FROM notifications WHERE id = ? AND user_id = ?', [req.params.id, req.user.id]);
  res.json({ message: 'Notification dismissed.' });
}));

export default router;
