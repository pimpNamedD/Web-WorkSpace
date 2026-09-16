import { Router } from 'express';
import bcrypt from 'bcryptjs';
import { one, query, run } from '../db.js';
import { requireAuth, requireAdmin } from '../middleware/auth.js';
import { asyncHandler, HttpError, nz, publicUser, toInt } from '../utils/helpers.js';
import { notify } from '../utils/notify.js';
import { searchListings } from '../utils/listingQuery.js';
import { archiveExpiredListings } from '../jobs/maintenance.js';

const router = Router();
router.use(requireAuth, requireAdmin);

/** Record every moderation action for accountability. */
async function audit(adminId, action, targetType, targetId, note = null) {
  await run(
    'INSERT INTO admin_actions (admin_id, action, target_type, target_id, note) VALUES (?, ?, ?, ?, ?)',
    [adminId, action, targetType, targetId, note],
  );
}

/**
 * GET /api/admin/stats - headline figures for the administrator dashboard
 * (1.4 "Administrator Dashboard").
 */
router.get('/stats', asyncHandler(async (_req, res) => {
  const users = await one(
    `SELECT COUNT(*) AS total,
            SUM(verification_status = 'verified') AS verified,
            SUM(verification_status = 'pending')  AS pending,
            SUM(is_suspended = 1)                 AS suspended,
            SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS new_this_week
       FROM users`,
  );
  const listings = await one(
    `SELECT COUNT(*) AS total,
            SUM(status = 'active')   AS active,
            SUM(status = 'archived') AS archived,
            SUM(status = 'removed')  AS removed,
            SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS new_this_week
       FROM listings`,
  );
  const byType = await query(
    "SELECT type, COUNT(*) AS total, SUM(status = 'active') AS active FROM listings GROUP BY type",
  );
  const reports = await one(
    `SELECT COUNT(*) AS total, SUM(status = 'open') AS open,
            SUM(status = 'reviewing') AS reviewing, SUM(status = 'resolved') AS resolved
       FROM reports`,
  );
  const engagement = await one(
    `SELECT (SELECT COUNT(*) FROM messages)     AS messages,
            (SELECT COUNT(*) FROM reviews)      AS reviews,
            (SELECT COUNT(*) FROM applications) AS applications,
            (SELECT ROUND(AVG(rating), 2) FROM reviews) AS average_rating`,
  );
  const signupTrend = await query(
    `SELECT DATE(created_at) AS day, COUNT(*) AS count FROM users
      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
      GROUP BY DATE(created_at) ORDER BY day`,
  );
  const listingTrend = await query(
    `SELECT DATE(created_at) AS day, COUNT(*) AS count FROM listings
      WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
      GROUP BY DATE(created_at) ORDER BY day`,
  );

  res.json({ users, listings, by_type: byType, reports, engagement, trends: { signups: signupTrend, listings: listingTrend } });
}));

/** GET /api/admin/users - manage user accounts. */
router.get('/users', asyncHandler(async (req, res) => {
  const limit = toInt(req.query.limit, 30, { min: 1, max: 100 });
  const page = toInt(req.query.page, 1, { min: 1 });
  const where = [];
  const params = [];

  if (req.query.q) {
    where.push('(full_name LIKE ? OR email LIKE ? OR student_id LIKE ?)');
    const term = `%${req.query.q}%`;
    params.push(term, term, term);
  }
  if (req.query.status) { where.push('verification_status = ?'); params.push(req.query.status); }
  if (req.query.role) { where.push('role = ?'); params.push(req.query.role); }
  if (req.query.suspended !== undefined && req.query.suspended !== '') {
    where.push('is_suspended = ?');
    params.push(['1', 'true'].includes(String(req.query.suspended).toLowerCase()) ? 1 : 0);
  }
  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';

  const users = await query(
    `SELECT u.*,
            (SELECT COUNT(*) FROM listings l WHERE l.user_id = u.id) AS listing_count,
            (SELECT COUNT(*) FROM reports r WHERE r.target_type = 'user' AND r.target_id = u.id) AS report_count,
            (SELECT ROUND(AVG(rating), 2) FROM reviews rv WHERE rv.reviewee_id = u.id) AS average_rating
       FROM users u ${whereSql}
      ORDER BY u.created_at DESC LIMIT ${limit} OFFSET ${(page - 1) * limit}`,
    params,
  );
  const [{ total }] = await query(`SELECT COUNT(*) AS total FROM users u ${whereSql}`, params);

  res.json({
    users: users.map((u) => ({ ...publicUser(u), email: u.email, verification_note: u.verification_note, suspension_reason: u.suspension_reason })),
    pagination: { page, limit, total: Number(total), pages: Math.max(1, Math.ceil(Number(total) / limit)) },
  });
}));

/** GET /api/admin/verifications - the pending student verification queue. */
router.get('/verifications', asyncHandler(async (req, res) => {
  const status = req.query.status ?? 'pending';
  const rows = await query(
    'SELECT * FROM users WHERE verification_status = ? ORDER BY updated_at ASC',
    [status],
  );
  res.json({ users: rows.map((u) => ({ ...publicUser(u), email: u.email, verification_note: u.verification_note })) });
}));

/** POST /api/admin/users/:id/verify - approve or reject a verification request. */
router.post('/users/:id/verify', asyncHandler(async (req, res) => {
  const { decision, note } = req.body;
  if (!['verified', 'rejected'].includes(decision)) throw new HttpError(400, 'Decision must be "verified" or "rejected".');

  const user = await one('SELECT * FROM users WHERE id = ?', [req.params.id]);
  if (!user) throw new HttpError(404, 'That user does not exist.');

  await run(
    'UPDATE users SET verification_status = ?, verification_note = ?, verified_at = ? WHERE id = ?',
    [decision, nz(note), decision === 'verified' ? new Date() : null, user.id],
  );
  await audit(req.user.id, `verification_${decision}`, 'user', user.id, nz(note));
  await notify(user.id, {
    type: 'verification_result',
    title: decision === 'verified' ? 'Your student account is verified' : 'Verification was not approved',
    body: decision === 'verified'
      ? 'You can now post listings across all Campus Connect modules.'
      : `An administrator could not verify your details. ${note ?? 'Please resubmit with accurate information.'}`,
    link: '/settings',
  });

  res.json({ user: publicUser(await one('SELECT * FROM users WHERE id = ?', [user.id])) });
}));

/** POST /api/admin/users/:id/suspend - suspend or restore an account. */
router.post('/users/:id/suspend', asyncHandler(async (req, res) => {
  const user = await one('SELECT * FROM users WHERE id = ?', [req.params.id]);
  if (!user) throw new HttpError(404, 'That user does not exist.');
  if (user.role === 'admin') throw new HttpError(400, 'Administrator accounts cannot be suspended.');

  const suspend = req.body.suspend !== false;
  await run('UPDATE users SET is_suspended = ?, suspension_reason = ? WHERE id = ?', [
    suspend ? 1 : 0, suspend ? nz(req.body.reason) : null, user.id,
  ]);
  if (suspend) await run("UPDATE listings SET status = 'archived' WHERE user_id = ? AND status = 'active'", [user.id]);

  await audit(req.user.id, suspend ? 'user_suspended' : 'user_restored', 'user', user.id, nz(req.body.reason));
  await notify(user.id, {
    type: 'account_status',
    title: suspend ? 'Your account has been suspended' : 'Your account has been restored',
    body: suspend ? (req.body.reason ?? 'Contact the administrators for more information.') : 'You can sign in and post again.',
    link: '/settings',
  });

  res.json({ user: publicUser(await one('SELECT * FROM users WHERE id = ?', [user.id])) });
}));

/** POST /api/admin/users/:id/role - promote or demote an account. */
router.post('/users/:id/role', asyncHandler(async (req, res) => {
  if (!['student', 'employer', 'admin'].includes(req.body.role)) throw new HttpError(400, 'Invalid role.');
  const user = await one('SELECT * FROM users WHERE id = ?', [req.params.id]);
  if (!user) throw new HttpError(404, 'That user does not exist.');
  if (user.id === req.user.id) throw new HttpError(400, 'You cannot change your own role.');

  await run('UPDATE users SET role = ? WHERE id = ?', [req.body.role, user.id]);
  await audit(req.user.id, 'role_changed', 'user', user.id, `${user.role} -> ${req.body.role}`);
  res.json({ user: publicUser(await one('SELECT * FROM users WHERE id = ?', [user.id])) });
}));

/** POST /api/admin/users - create an account (e.g. a second administrator). */
router.post('/users', asyncHandler(async (req, res) => {
  const { full_name, email, password, role = 'student' } = req.body;
  if (!full_name || !email || !password) throw new HttpError(400, 'Name, e-mail and password are required.');
  if (await one('SELECT id FROM users WHERE email = ?', [String(email).toLowerCase()])) {
    throw new HttpError(409, 'An account with that e-mail already exists.');
  }
  const result = await run(
    `INSERT INTO users (full_name, email, password_hash, role, verification_status, verified_at)
     VALUES (?, ?, ?, ?, 'verified', NOW())`,
    [full_name, String(email).toLowerCase(), await bcrypt.hash(password, 10), role],
  );
  await audit(req.user.id, 'user_created', 'user', result.insertId, role);
  res.status(201).json({ user: publicUser(await one('SELECT * FROM users WHERE id = ?', [result.insertId])) });
}));

/** GET /api/admin/listings - moderate content across every module. */
router.get('/listings', asyncHandler(async (req, res) => {
  const result = await searchListings({ ...req.query, limit: req.query.limit ?? 30 }, req.user.id, { includeAllStatuses: true });
  res.json(result);
}));

/** POST /api/admin/listings/:id/moderate - remove, restore or archive a listing. */
router.post('/listings/:id/moderate', asyncHandler(async (req, res) => {
  const { action, note } = req.body;
  if (!['remove', 'restore', 'archive'].includes(action)) throw new HttpError(400, 'Action must be remove, restore or archive.');

  const listing = await one('SELECT * FROM listings WHERE id = ?', [req.params.id]);
  if (!listing) throw new HttpError(404, 'That listing does not exist.');

  const status = action === 'remove' ? 'removed' : action === 'archive' ? 'archived' : 'active';
  await run('UPDATE listings SET status = ? WHERE id = ?', [status, listing.id]);
  await audit(req.user.id, `listing_${action}`, 'listing', listing.id, nz(note));

  await notify(listing.user_id, {
    type: 'listing_moderated',
    title: action === 'restore' ? 'Your listing was restored' : `Your listing was ${status}`,
    body: `"${listing.title}"${note ? ` - ${note}` : ''}`,
    link: '/dashboard/listings',
  });

  res.json({ listing: await one('SELECT * FROM listings WHERE id = ?', [listing.id]) });
}));

/** GET /api/admin/reports - the moderation queue. */
router.get('/reports', asyncHandler(async (req, res) => {
  const where = [];
  const params = [];
  if (req.query.status) { where.push('r.status = ?'); params.push(req.query.status); }
  if (req.query.target_type) { where.push('r.target_type = ?'); params.push(req.query.target_type); }
  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';

  const reports = await query(
    `SELECT r.*,
            rep.full_name AS reporter_name, rep.email AS reporter_email,
            adm.full_name AS handled_by_name,
            CASE WHEN r.target_type = 'listing'
                 THEN (SELECT title FROM listings WHERE id = r.target_id)
                 ELSE (SELECT full_name FROM users WHERE id = r.target_id) END AS target_label,
            CASE WHEN r.target_type = 'listing'
                 THEN (SELECT status FROM listings WHERE id = r.target_id)
                 ELSE (SELECT IF(is_suspended, 'suspended', 'active') FROM users WHERE id = r.target_id) END AS target_status,
            CASE WHEN r.target_type = 'listing'
                 THEN (SELECT user_id FROM listings WHERE id = r.target_id)
                 ELSE r.target_id END AS target_owner_id
       FROM reports r
       JOIN users rep ON rep.id = r.reporter_id
       LEFT JOIN users adm ON adm.id = r.handled_by
       ${whereSql}
      ORDER BY FIELD(r.status, 'open', 'reviewing', 'resolved', 'dismissed'), r.created_at DESC`,
    params,
  );
  res.json({ reports });
}));

/** PATCH /api/admin/reports/:id - progress or close a report. */
router.patch('/reports/:id', asyncHandler(async (req, res) => {
  const { status, admin_note } = req.body;
  if (!['open', 'reviewing', 'resolved', 'dismissed'].includes(status)) throw new HttpError(400, 'Invalid report status.');

  const report = await one('SELECT * FROM reports WHERE id = ?', [req.params.id]);
  if (!report) throw new HttpError(404, 'That report does not exist.');

  const closing = ['resolved', 'dismissed'].includes(status);
  await run(
    'UPDATE reports SET status = ?, admin_note = ?, handled_by = ?, handled_at = ? WHERE id = ?',
    [status, nz(admin_note), req.user.id, closing ? new Date() : null, report.id],
  );
  await audit(req.user.id, `report_${status}`, report.target_type, report.target_id, nz(admin_note));

  if (closing) {
    await notify(report.reporter_id, {
      type: 'report_update',
      title: `Your report was ${status}`,
      body: admin_note ?? 'An administrator has reviewed the report you submitted.',
      link: '/dashboard/reports',
    });
  }
  res.json({ report: await one('SELECT * FROM reports WHERE id = ?', [report.id]) });
}));

/** GET /api/admin/audit - the moderation audit trail. */
router.get('/audit', asyncHandler(async (req, res) => {
  const limit = toInt(req.query.limit, 50, { min: 1, max: 200 });
  const rows = await query(
    `SELECT a.*, u.full_name AS admin_name FROM admin_actions a
       JOIN users u ON u.id = a.admin_id
      ORDER BY a.created_at DESC LIMIT ${limit}`,
  );
  res.json({ actions: rows });
}));

/** POST /api/admin/maintenance/run - trigger listing expiry manually. */
router.post('/maintenance/run', asyncHandler(async (req, res) => {
  const archived = await archiveExpiredListings();
  await audit(req.user.id, 'maintenance_run', 'system', 0, `archived ${archived}`);
  res.json({ archived });
}));

export default router;
