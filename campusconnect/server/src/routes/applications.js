import { Router } from 'express';
import { one, query, run } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { asyncHandler, HttpError, nz } from '../utils/helpers.js';
import { notify } from '../utils/notify.js';

const router = Router();

const STATUSES = ['submitted', 'reviewed', 'shortlisted', 'rejected', 'accepted'];

/**
 * POST /api/applications - apply for a job or internship (1.5 Job Board).
 */
router.post('/', requireAuth, asyncHandler(async (req, res) => {
  const { listing_id, cover_note } = req.body;
  if (!listing_id) throw new HttpError(400, 'A job listing must be specified.');

  const listing = await one('SELECT * FROM listings WHERE id = ?', [listing_id]);
  if (!listing || listing.type !== 'job') throw new HttpError(404, 'That job listing does not exist.');
  if (listing.status !== 'active') throw new HttpError(400, 'This opportunity is no longer accepting applications.');
  if (listing.user_id === req.user.id) throw new HttpError(400, 'You cannot apply to your own posting.');
  if (listing.deadline && new Date(listing.deadline) < new Date(new Date().toDateString())) {
    throw new HttpError(400, 'The application deadline for this opportunity has passed.');
  }

  const existing = await one('SELECT id FROM applications WHERE listing_id = ? AND applicant_id = ?', [listing_id, req.user.id]);
  if (existing) throw new HttpError(409, 'You have already applied for this opportunity.');

  const result = await run(
    'INSERT INTO applications (listing_id, applicant_id, cover_note) VALUES (?, ?, ?)',
    [listing_id, req.user.id, nz(cover_note)],
  );

  await notify(listing.user_id, {
    type: 'application_received',
    title: 'New application received',
    body: `${req.user.full_name} applied for "${listing.title}".`,
    link: `/dashboard/applications?listing=${listing.id}`,
  });

  res.status(201).json({ application: await one('SELECT * FROM applications WHERE id = ?', [result.insertId]) });
}));

/** GET /api/applications/mine - opportunities I have applied for. */
router.get('/mine', requireAuth, asyncHandler(async (req, res) => {
  const rows = await query(
    `SELECT a.*, l.title, l.company, l.location, l.status AS listing_status, l.deadline,
            u.full_name AS employer_name, u.id AS employer_id
       FROM applications a
       JOIN listings l ON l.id = a.listing_id
       JOIN users u ON u.id = l.user_id
      WHERE a.applicant_id = ?
      ORDER BY a.created_at DESC`,
    [req.user.id],
  );
  res.json({ applications: rows });
}));

/** GET /api/applications/received - applications to listings I posted. */
router.get('/received', requireAuth, asyncHandler(async (req, res) => {
  const params = [req.user.id];
  let extra = '';
  if (req.query.listing) {
    extra = ' AND a.listing_id = ?';
    params.push(req.query.listing);
  }
  const rows = await query(
    `SELECT a.*, l.title, l.id AS listing_id,
            u.id AS applicant_id, u.full_name AS applicant_name, u.email AS applicant_email,
            u.phone AS applicant_phone, u.program, u.year_of_study, u.university,
            u.verification_status AS applicant_verification, u.avatar_url AS applicant_avatar,
            (SELECT ROUND(AVG(rating), 2) FROM reviews r WHERE r.reviewee_id = u.id) AS applicant_rating
       FROM applications a
       JOIN listings l ON l.id = a.listing_id
       JOIN users u ON u.id = a.applicant_id
      WHERE l.user_id = ?${extra}
      ORDER BY a.created_at DESC`,
    params,
  );
  res.json({ applications: rows });
}));

/** PATCH /api/applications/:id - employer updates an application's status. */
router.patch('/:id', requireAuth, asyncHandler(async (req, res) => {
  const app = await one(
    `SELECT a.*, l.user_id AS owner_id, l.title FROM applications a
       JOIN listings l ON l.id = a.listing_id WHERE a.id = ?`,
    [req.params.id],
  );
  if (!app) throw new HttpError(404, 'That application does not exist.');
  if (app.owner_id !== req.user.id && req.user.role !== 'admin') {
    throw new HttpError(403, 'Only the employer who posted the job can update applications.');
  }
  if (!STATUSES.includes(req.body.status)) throw new HttpError(400, `Status must be one of: ${STATUSES.join(', ')}`);

  await run('UPDATE applications SET status = ? WHERE id = ?', [req.body.status, app.id]);
  await notify(app.applicant_id, {
    type: 'application_update',
    title: `Application ${req.body.status}`,
    body: `Your application for "${app.title}" is now marked as ${req.body.status}.`,
    link: '/dashboard/applications',
  });

  res.json({ application: await one('SELECT * FROM applications WHERE id = ?', [app.id]) });
}));

/** DELETE /api/applications/:id - withdraw an application. */
router.delete('/:id', requireAuth, asyncHandler(async (req, res) => {
  const app = await one('SELECT * FROM applications WHERE id = ?', [req.params.id]);
  if (!app) throw new HttpError(404, 'That application does not exist.');
  if (app.applicant_id !== req.user.id) throw new HttpError(403, 'You can only withdraw your own application.');
  await run('DELETE FROM applications WHERE id = ?', [app.id]);
  res.json({ message: 'Application withdrawn.' });
}));

export default router;
