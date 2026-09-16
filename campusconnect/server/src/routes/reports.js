import { Router } from 'express';
import { one, query, run } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { asyncHandler, HttpError, nz, requireFields } from '../utils/helpers.js';
import { notify } from '../utils/notify.js';
import { REPORT_REASONS } from '../utils/listingTypes.js';

const router = Router();

/**
 * POST /api/reports - flag a suspicious listing or user. Reports feed straight
 * into the administrator dashboard (1.4.2 "Reporting and Moderation Integration").
 */
router.post('/', requireAuth, asyncHandler(async (req, res) => {
  requireFields(req.body, ['target_type', 'target_id', 'reason']);
  const { target_type, target_id, reason, details } = req.body;

  if (!['listing', 'user'].includes(target_type)) throw new HttpError(400, 'You can report a listing or a user.');
  if (!REPORT_REASONS.includes(reason)) throw new HttpError(400, 'Choose a valid reason for the report.');

  const target = target_type === 'listing'
    ? await one('SELECT id, title AS label, user_id FROM listings WHERE id = ?', [target_id])
    : await one('SELECT id, full_name AS label, id AS user_id FROM users WHERE id = ?', [target_id]);
  if (!target) throw new HttpError(404, 'The item you are reporting does not exist.');
  if (target.user_id === req.user.id) throw new HttpError(400, 'You cannot report your own content.');

  const duplicate = await one(
    "SELECT id FROM reports WHERE reporter_id = ? AND target_type = ? AND target_id = ? AND status IN ('open','reviewing')",
    [req.user.id, target_type, target_id],
  );
  if (duplicate) throw new HttpError(409, 'You have already reported this and an administrator is reviewing it.');

  const result = await run(
    'INSERT INTO reports (reporter_id, target_type, target_id, reason, details) VALUES (?, ?, ?, ?, ?)',
    [req.user.id, target_type, target_id, reason, nz(details)],
  );

  const admins = await query("SELECT id FROM users WHERE role = 'admin'");
  await Promise.all(admins.map((a) => notify(a.id, {
    type: 'report_filed',
    title: `New report: ${reason}`,
    body: `${req.user.full_name} reported the ${target_type} "${target.label}".`,
    link: '/admin/reports',
    email: false,
  })));

  await notify(req.user.id, {
    type: 'report_submitted',
    title: 'Report submitted',
    body: 'Thank you. An administrator will review your report and you will be notified of the outcome.',
    link: '/dashboard/reports',
    email: false,
  });

  res.status(201).json({ report: await one('SELECT * FROM reports WHERE id = ?', [result.insertId]) });
}));

/** GET /api/reports/mine - track reports I have submitted (1.5 Notifications). */
router.get('/mine', requireAuth, asyncHandler(async (req, res) => {
  const reports = await query(
    `SELECT r.*,
            CASE WHEN r.target_type = 'listing'
                 THEN (SELECT title FROM listings WHERE id = r.target_id)
                 ELSE (SELECT full_name FROM users WHERE id = r.target_id) END AS target_label
       FROM reports r WHERE r.reporter_id = ? ORDER BY r.created_at DESC`,
    [req.user.id],
  );
  res.json({ reports });
}));

/** GET /api/reports/reasons */
router.get('/reasons', (_req, res) => res.json({ reasons: REPORT_REASONS }));

export default router;
