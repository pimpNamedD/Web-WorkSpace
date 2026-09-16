import { Router } from 'express';
import { one, query, run } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { upload, fileUrl } from '../middleware/upload.js';
import { asyncHandler, HttpError, publicUser, selfUser, nz, toInt } from '../utils/helpers.js';
import { notify } from '../utils/notify.js';

const router = Router();

/** Aggregate rating + counts shown on every profile card (1.5 Profile Management). */
async function profileStats(userId) {
  const rating = await one(
    'SELECT COUNT(*) AS review_count, ROUND(AVG(rating), 2) AS average_rating FROM reviews WHERE reviewee_id = ?',
    [userId],
  );
  const listings = await one(
    "SELECT COUNT(*) AS total, SUM(status = 'active') AS active FROM listings WHERE user_id = ? AND status <> 'removed'",
    [userId],
  );
  return {
    review_count: Number(rating.review_count) || 0,
    average_rating: rating.average_rating === null ? null : Number(rating.average_rating),
    listing_count: Number(listings.total) || 0,
    active_listing_count: Number(listings.active) || 0,
  };
}

export { profileStats };

/**
 * GET /api/users/:id - public profile with verification status, rating and
 * review history (1.4.2 "Verification History").
 */
router.get('/:id', asyncHandler(async (req, res) => {
  const user = await one('SELECT * FROM users WHERE id = ?', [req.params.id]);
  if (!user) throw new HttpError(404, 'That profile does not exist.');

  const listings = await query(
    `SELECT l.*, (SELECT url FROM listing_images i WHERE i.listing_id = l.id ORDER BY sort_order LIMIT 1) AS image
       FROM listings l
      WHERE l.user_id = ? AND l.status IN ('active','sold','closed','filled','resolved')
      ORDER BY l.created_at DESC LIMIT 50`,
    [req.params.id],
  );
  const reviews = await query(
    `SELECT r.*, u.full_name AS reviewer_name, u.avatar_url AS reviewer_avatar,
            u.verification_status AS reviewer_verification, l.title AS listing_title
       FROM reviews r
       JOIN users u ON u.id = r.reviewer_id
       LEFT JOIN listings l ON l.id = r.listing_id
      WHERE r.reviewee_id = ?
      ORDER BY r.created_at DESC LIMIT 50`,
    [req.params.id],
  );

  res.json({ user: publicUser(user), stats: await profileStats(user.id), listings, reviews });
}));

/** PATCH /api/users/me - update own profile. */
router.patch('/me', requireAuth, asyncHandler(async (req, res) => {
  const editable = ['full_name', 'phone', 'bio', 'program', 'year_of_study', 'university', 'student_id'];
  const sets = [];
  const params = [];
  for (const field of editable) {
    if (req.body[field] !== undefined) {
      sets.push(`${field} = ?`);
      params.push(nz(req.body[field]));
    }
  }
  if (req.body.email_notifications !== undefined) {
    sets.push('email_notifications = ?');
    params.push(req.body.email_notifications ? 1 : 0);
  }
  if (!sets.length) throw new HttpError(400, 'Nothing to update.');
  params.push(req.user.id);
  await run(`UPDATE users SET ${sets.join(', ')} WHERE id = ?`, params);
  res.json({ user: selfUser(await one('SELECT * FROM users WHERE id = ?', [req.user.id])) });
}));

/** POST /api/users/me/avatar - profile picture upload. */
router.post('/me/avatar', requireAuth, upload.single('avatar'), asyncHandler(async (req, res) => {
  if (!req.file) throw new HttpError(400, 'Choose an image to upload.');
  const url = fileUrl(req.file.filename);
  await run('UPDATE users SET avatar_url = ? WHERE id = ?', [url, req.user.id]);
  res.json({ avatar_url: url });
}));

/**
 * POST /api/users/me/verification - submit student details for approval.
 * Objective 1: "only verified students can access the system".
 */
router.post('/me/verification', requireAuth, asyncHandler(async (req, res) => {
  if (req.user.verification_status === 'verified') {
    throw new HttpError(400, 'Your account is already verified.');
  }
  const { student_id, university, program, year_of_study } = req.body;
  if (!student_id || !university) throw new HttpError(400, 'Student number and university are required.');

  await run(
    `UPDATE users SET student_id = ?, university = ?, program = ?, year_of_study = ?,
            verification_status = 'pending', verification_note = NULL
      WHERE id = ?`,
    [student_id, university, nz(program), nz(year_of_study), req.user.id],
  );

  const admins = await query("SELECT id FROM users WHERE role = 'admin'");
  await Promise.all(admins.map((a) => notify(a.id, {
    type: 'verification_request',
    title: 'New student verification request',
    body: `${req.user.full_name} (${student_id}) submitted details for verification.`,
    link: '/admin/verifications',
    email: false,
  })));

  res.json({ user: selfUser(await one('SELECT * FROM users WHERE id = ?', [req.user.id])) });
}));

/** GET /api/users - directory search, used by the admin console and @mentions. */
router.get('/', requireAuth, asyncHandler(async (req, res) => {
  const limit = toInt(req.query.limit, 20, { min: 1, max: 50 });
  const search = req.query.q ? `%${req.query.q}%` : null;
  const rows = search
    ? await query(
      `SELECT id, full_name, email, role, verification_status, avatar_url FROM users
        WHERE (full_name LIKE ? OR email LIKE ? OR student_id LIKE ?) AND is_suspended = 0
        ORDER BY full_name LIMIT ${limit}`,
      [search, search, search],
    )
    : await query(
      `SELECT id, full_name, email, role, verification_status, avatar_url FROM users
        WHERE is_suspended = 0 ORDER BY created_at DESC LIMIT ${limit}`,
    );
  res.json({ users: rows });
}));

export default router;
