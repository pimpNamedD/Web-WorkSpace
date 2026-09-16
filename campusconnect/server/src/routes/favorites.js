import { Router } from 'express';
import { one, run } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { asyncHandler, HttpError } from '../utils/helpers.js';
import { searchListings } from '../utils/listingQuery.js';

const router = Router();

/** GET /api/favorites - saved listings (1.4.1 "Saved Searches and Favorites"). */
router.get('/', requireAuth, asyncHandler(async (req, res) => {
  const { listings, pagination } = await searchListings(
    { ...req.query, limit: req.query.limit ?? 60 },
    req.user.id,
    { includeAllStatuses: true },
  );
  const favorites = listings.filter((l) => l.is_favorite);
  res.json({ listings: favorites, pagination: { ...pagination, total: favorites.length } });
}));

/** POST /api/favorites/:listingId - add to favourites. */
router.post('/:listingId', requireAuth, asyncHandler(async (req, res) => {
  const listing = await one('SELECT id FROM listings WHERE id = ?', [req.params.listingId]);
  if (!listing) throw new HttpError(404, 'That listing no longer exists.');
  await run('INSERT IGNORE INTO favorites (user_id, listing_id) VALUES (?, ?)', [req.user.id, listing.id]);
  res.status(201).json({ is_favorite: true });
}));

/** DELETE /api/favorites/:listingId */
router.delete('/:listingId', requireAuth, asyncHandler(async (req, res) => {
  await run('DELETE FROM favorites WHERE user_id = ? AND listing_id = ?', [req.user.id, req.params.listingId]);
  res.json({ is_favorite: false });
}));

/** POST /api/favorites/:listingId/toggle - convenience for the heart button. */
router.post('/:listingId/toggle', requireAuth, asyncHandler(async (req, res) => {
  const existing = await one('SELECT 1 AS ok FROM favorites WHERE user_id = ? AND listing_id = ?', [req.user.id, req.params.listingId]);
  if (existing) {
    await run('DELETE FROM favorites WHERE user_id = ? AND listing_id = ?', [req.user.id, req.params.listingId]);
    return res.json({ is_favorite: false });
  }
  const listing = await one('SELECT id FROM listings WHERE id = ?', [req.params.listingId]);
  if (!listing) throw new HttpError(404, 'That listing no longer exists.');
  await run('INSERT INTO favorites (user_id, listing_id) VALUES (?, ?)', [req.user.id, listing.id]);
  return res.json({ is_favorite: true });
}));

export default router;
