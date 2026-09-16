import { Router } from 'express';
import { one, query, run } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { asyncHandler, HttpError, nz, requireFields } from '../utils/helpers.js';
import { notify } from '../utils/notify.js';

const router = Router();

/**
 * POST /api/reviews - rate another user after an interaction.
 * Objective 10: "a reviews, ratings and reporting mechanism".
 */
router.post('/', requireAuth, asyncHandler(async (req, res) => {
  requireFields(req.body, ['reviewee_id', 'rating']);
  const { reviewee_id, listing_id, rating, comment } = req.body;

  const score = Number(rating);
  if (!Number.isInteger(score) || score < 1 || score > 5) throw new HttpError(400, 'Rating must be a whole number from 1 to 5.');
  if (Number(reviewee_id) === req.user.id) throw new HttpError(400, 'You cannot review yourself.');

  const reviewee = await one('SELECT id, full_name FROM users WHERE id = ?', [reviewee_id]);
  if (!reviewee) throw new HttpError(404, 'That user does not exist.');

  if (listing_id) {
    const listing = await one('SELECT id, user_id, title FROM listings WHERE id = ?', [listing_id]);
    if (!listing) throw new HttpError(404, 'That listing does not exist.');
    if (listing.user_id !== Number(reviewee_id)) throw new HttpError(400, 'That listing does not belong to the person you are reviewing.');
  }

  const duplicate = await one(
    'SELECT id FROM reviews WHERE reviewer_id = ? AND reviewee_id = ? AND (listing_id <=> ?)',
    [req.user.id, reviewee_id, nz(listing_id)],
  );
  if (duplicate) throw new HttpError(409, 'You have already left a review for this interaction.');

  const result = await run(
    'INSERT INTO reviews (reviewer_id, reviewee_id, listing_id, rating, comment) VALUES (?, ?, ?, ?, ?)',
    [req.user.id, reviewee_id, nz(listing_id), score, nz(comment)],
  );

  await notify(reviewee_id, {
    type: 'review_received',
    title: `${req.user.full_name} left you a ${score}-star review`,
    body: comment ? String(comment).slice(0, 200) : null,
    link: `/profile/${reviewee_id}`,
  });

  res.status(201).json({ review: await one('SELECT * FROM reviews WHERE id = ?', [result.insertId]) });
}));

/** GET /api/reviews/user/:id - all reviews written about a user. */
router.get('/user/:id', asyncHandler(async (req, res) => {
  const reviews = await query(
    `SELECT r.*, u.full_name AS reviewer_name, u.avatar_url AS reviewer_avatar,
            u.verification_status AS reviewer_verification, l.title AS listing_title, l.type AS listing_type
       FROM reviews r
       JOIN users u ON u.id = r.reviewer_id
       LEFT JOIN listings l ON l.id = r.listing_id
      WHERE r.reviewee_id = ?
      ORDER BY r.created_at DESC`,
    [req.params.id],
  );
  const summary = await one(
    `SELECT COUNT(*) AS count, ROUND(AVG(rating), 2) AS average,
            SUM(rating = 5) AS five, SUM(rating = 4) AS four, SUM(rating = 3) AS three,
            SUM(rating = 2) AS two, SUM(rating = 1) AS one
       FROM reviews WHERE reviewee_id = ?`,
    [req.params.id],
  );
  res.json({ reviews, summary });
}));

/** GET /api/reviews/mine - reviews I have written. */
router.get('/mine', requireAuth, asyncHandler(async (req, res) => {
  const reviews = await query(
    `SELECT r.*, u.full_name AS reviewee_name, l.title AS listing_title
       FROM reviews r JOIN users u ON u.id = r.reviewee_id
       LEFT JOIN listings l ON l.id = r.listing_id
      WHERE r.reviewer_id = ? ORDER BY r.created_at DESC`,
    [req.user.id],
  );
  res.json({ reviews });
}));

/** DELETE /api/reviews/:id - withdraw your own review (admins may remove any). */
router.delete('/:id', requireAuth, asyncHandler(async (req, res) => {
  const review = await one('SELECT * FROM reviews WHERE id = ?', [req.params.id]);
  if (!review) throw new HttpError(404, 'That review does not exist.');
  if (review.reviewer_id !== req.user.id && req.user.role !== 'admin') {
    throw new HttpError(403, 'You can only delete your own review.');
  }
  await run('DELETE FROM reviews WHERE id = ?', [review.id]);
  res.json({ message: 'Review deleted.' });
}));

export default router;
