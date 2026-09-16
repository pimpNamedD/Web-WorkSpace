import { Router } from 'express';
import { config } from '../config.js';
import { one, query, run } from '../db.js';
import { requireAuth, requireVerified } from '../middleware/auth.js';
import { upload, fileUrl } from '../middleware/upload.js';
import { asyncHandler, HttpError, nz, nzNum, nzBool, requireFields, toInt } from '../utils/helpers.js';
import { LISTING_TYPES, isValidType } from '../utils/listingTypes.js';
import { searchListings } from '../utils/listingQuery.js';
import { notify } from '../utils/notify.js';
import { runSavedSearchAlerts } from '../jobs/savedSearchAlerts.js';

const router = Router();

const NUMERIC = new Set(['price', 'bedrooms', 'bathrooms', 'budget_min', 'budget_max']);
const BOOLEAN = new Set(['furnished']);
const DATE = new Set(['deadline', 'move_in_date', 'item_date']);

/** Map the request body onto the columns this listing type is allowed to set. */
function collectFields(type, body) {
  const def = LISTING_TYPES[type];
  const cols = {};
  for (const field of def.fields) {
    if (body[field] === undefined) continue;
    if (NUMERIC.has(field)) cols[field] = nzNum(body[field]);
    else if (BOOLEAN.has(field)) cols[field] = nzBool(body[field]);
    else if (DATE.has(field)) cols[field] = nz(body[field]);
    else cols[field] = nz(body[field]);
  }
  if (def.fields.includes('price_unit') && !cols.price_unit) cols.price_unit = def.defaultPriceUnit;
  if (type === 'job' && body.job_type === undefined && body.category) cols.job_type = body.category;
  if (type === 'tutor' && body.subject === undefined && body.category) cols.subject = body.category;
  return cols;
}

async function fullListing(id, viewerId = null) {
  const listing = await one(
    `SELECT l.*,
            u.full_name AS seller_name, u.email AS seller_email, u.phone AS seller_phone,
            u.avatar_url AS seller_avatar, u.verification_status AS seller_verification,
            u.university AS seller_university, u.program AS seller_program, u.created_at AS seller_joined,
            (SELECT ROUND(AVG(rating), 2) FROM reviews r WHERE r.reviewee_id = l.user_id) AS seller_rating,
            (SELECT COUNT(*) FROM reviews r WHERE r.reviewee_id = l.user_id) AS seller_review_count,
            (SELECT COUNT(*) FROM favorites f WHERE f.listing_id = l.id) AS favorite_count
       FROM listings l JOIN users u ON u.id = l.user_id
      WHERE l.id = ?`,
    [id],
  );
  if (!listing) return null;
  listing.images = await query('SELECT id, url, sort_order FROM listing_images WHERE listing_id = ? ORDER BY sort_order', [id]);
  if (viewerId) {
    const fav = await one('SELECT 1 AS ok FROM favorites WHERE user_id = ? AND listing_id = ?', [viewerId, id]);
    listing.is_favorite = Boolean(fav);
    if (listing.type === 'job') {
      const app = await one('SELECT id, status, created_at FROM applications WHERE listing_id = ? AND applicant_id = ?', [id, viewerId]);
      listing.my_application = app ?? null;
    }
  } else {
    listing.is_favorite = false;
  }
  if (listing.type === 'job') {
    const [{ c }] = await query('SELECT COUNT(*) AS c FROM applications WHERE listing_id = ?', [id]);
    listing.application_count = Number(c);
  }
  return listing;
}

export { fullListing };

/**
 * GET /api/listings
 * Unified browse + advanced filter endpoint for all six modules.
 */
router.get('/', asyncHandler(async (req, res) => {
  const result = await searchListings(req.query, req.user?.id ?? null);
  res.json(result);
}));

/** GET /api/listings/featured - home page highlights, newest per module. */
router.get('/featured', asyncHandler(async (req, res) => {
  const perType = toInt(req.query.per_type, 4, { min: 1, max: 12 });
  const out = {};
  for (const type of Object.keys(LISTING_TYPES)) {
    const { listings } = await searchListings({ type, limit: perType, sort: 'newest' }, req.user?.id ?? null);
    out[type] = listings;
  }
  res.json({ featured: out });
}));

/** GET /api/listings/mine - the signed-in user's own posts. */
router.get('/mine', requireAuth, asyncHandler(async (req, res) => {
  const result = await searchListings(
    { ...req.query, user_id: req.user.id, limit: req.query.limit ?? 60 },
    req.user.id,
    { includeAllStatuses: true },
  );
  res.json(result);
}));

/** GET /api/listings/:id */
router.get('/:id', asyncHandler(async (req, res) => {
  const listing = await fullListing(req.params.id, req.user?.id ?? null);
  if (!listing) throw new HttpError(404, 'That listing no longer exists.');
  if (listing.status === 'removed' && req.user?.role !== 'admin' && req.user?.id !== listing.user_id) {
    throw new HttpError(404, 'That listing has been removed by an administrator.');
  }
  if (req.user?.id !== listing.user_id) {
    await run('UPDATE listings SET views = views + 1 WHERE id = ?', [req.params.id]);
  }
  res.json({ listing });
}));

/**
 * POST /api/listings
 * Publishes a listing in any module. Only verified accounts may post.
 */
router.post('/', requireAuth, requireVerified, upload.array('images', 6), asyncHandler(async (req, res) => {
  requireFields(req.body, ['type', 'title', 'description']);
  const { type, title, description } = req.body;
  if (!isValidType(type)) throw new HttpError(400, 'Unknown listing type.');
  if (String(title).trim().length < 4) throw new HttpError(400, 'Give your listing a clearer title.');

  const cols = collectFields(type, req.body);
  const ttl = toInt(req.body.ttl_days, config.listings.defaultTtlDays, { min: 1, max: 365 });

  const keys = Object.keys(cols);
  const result = await run(
    `INSERT INTO listings (user_id, type, title, description, expires_at${keys.length ? ', ' + keys.join(', ') : ''})
     VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ${ttl} DAY)${keys.length ? ', ' + keys.map(() => '?').join(', ') : ''})`,
    [req.user.id, type, String(title).trim(), String(description).trim(), ...keys.map((k) => cols[k])],
  );

  const listingId = result.insertId;
  if (req.files?.length) {
    await Promise.all(req.files.map((f, i) => run(
      'INSERT INTO listing_images (listing_id, url, sort_order) VALUES (?, ?, ?)',
      [listingId, fileUrl(f.filename), i],
    )));
  }

  // Alert anyone whose saved search matches this new post (1.4.1).
  runSavedSearchAlerts(listingId).catch((e) => console.error('[saved-search]', e.message));

  res.status(201).json({ listing: await fullListing(listingId, req.user.id) });
}));

/** PATCH /api/listings/:id - edit own listing (admins may edit any). */
router.patch('/:id', requireAuth, asyncHandler(async (req, res) => {
  const listing = await one('SELECT * FROM listings WHERE id = ?', [req.params.id]);
  if (!listing) throw new HttpError(404, 'That listing no longer exists.');
  if (listing.user_id !== req.user.id && req.user.role !== 'admin') {
    throw new HttpError(403, 'You can only edit your own listings.');
  }

  const cols = collectFields(listing.type, req.body);
  if (req.body.title !== undefined) cols.title = String(req.body.title).trim();
  if (req.body.description !== undefined) cols.description = String(req.body.description).trim();
  if (req.body.status !== undefined) {
    const allowed = ['active', LISTING_TYPES[listing.type].closeStatus, 'archived'];
    if (!allowed.includes(req.body.status)) throw new HttpError(400, 'Invalid status for this listing type.');
    cols.status = req.body.status;
  }
  if (req.body.ttl_days !== undefined) {
    const ttl = toInt(req.body.ttl_days, config.listings.defaultTtlDays, { min: 1, max: 365 });
    await run(`UPDATE listings SET expires_at = DATE_ADD(NOW(), INTERVAL ${ttl} DAY) WHERE id = ?`, [listing.id]);
  }

  const keys = Object.keys(cols);
  if (keys.length) {
    await run(
      `UPDATE listings SET ${keys.map((k) => `${k} = ?`).join(', ')} WHERE id = ?`,
      [...keys.map((k) => cols[k]), listing.id],
    );
  }
  res.json({ listing: await fullListing(listing.id, req.user.id) });
}));

/** POST /api/listings/:id/renew - extend an expiring or archived listing. */
router.post('/:id/renew', requireAuth, asyncHandler(async (req, res) => {
  const listing = await one('SELECT * FROM listings WHERE id = ?', [req.params.id]);
  if (!listing) throw new HttpError(404, 'That listing no longer exists.');
  if (listing.user_id !== req.user.id) throw new HttpError(403, 'You can only renew your own listings.');
  if (listing.status === 'removed') throw new HttpError(400, 'A listing removed by an administrator cannot be renewed.');

  const ttl = toInt(req.body?.ttl_days, config.listings.defaultTtlDays, { min: 1, max: 365 });
  await run(
    `UPDATE listings SET status = 'active', expires_at = DATE_ADD(NOW(), INTERVAL ${ttl} DAY) WHERE id = ?`,
    [listing.id],
  );
  res.json({ listing: await fullListing(listing.id, req.user.id) });
}));

/** POST /api/listings/:id/images - add more photos. */
router.post('/:id/images', requireAuth, upload.array('images', 6), asyncHandler(async (req, res) => {
  const listing = await one('SELECT * FROM listings WHERE id = ?', [req.params.id]);
  if (!listing) throw new HttpError(404, 'That listing no longer exists.');
  if (listing.user_id !== req.user.id) throw new HttpError(403, 'You can only edit your own listings.');
  if (!req.files?.length) throw new HttpError(400, 'Choose at least one image.');

  const [{ c }] = await query('SELECT COUNT(*) AS c FROM listing_images WHERE listing_id = ?', [listing.id]);
  await Promise.all(req.files.map((f, i) => run(
    'INSERT INTO listing_images (listing_id, url, sort_order) VALUES (?, ?, ?)',
    [listing.id, fileUrl(f.filename), Number(c) + i],
  )));
  res.status(201).json({ listing: await fullListing(listing.id, req.user.id) });
}));

/** DELETE /api/listings/:id/images/:imageId */
router.delete('/:id/images/:imageId', requireAuth, asyncHandler(async (req, res) => {
  const listing = await one('SELECT * FROM listings WHERE id = ?', [req.params.id]);
  if (!listing) throw new HttpError(404, 'That listing no longer exists.');
  if (listing.user_id !== req.user.id && req.user.role !== 'admin') throw new HttpError(403, 'Not your listing.');
  await run('DELETE FROM listing_images WHERE id = ? AND listing_id = ?', [req.params.imageId, listing.id]);
  res.json({ listing: await fullListing(listing.id, req.user.id) });
}));

/** DELETE /api/listings/:id */
router.delete('/:id', requireAuth, asyncHandler(async (req, res) => {
  const listing = await one('SELECT * FROM listings WHERE id = ?', [req.params.id]);
  if (!listing) throw new HttpError(404, 'That listing no longer exists.');
  if (listing.user_id !== req.user.id && req.user.role !== 'admin') {
    throw new HttpError(403, 'You can only delete your own listings.');
  }
  if (req.user.role === 'admin' && listing.user_id !== req.user.id) {
    await run("UPDATE listings SET status = 'removed' WHERE id = ?", [listing.id]);
    await notify(listing.user_id, {
      type: 'listing_removed',
      title: 'Your listing was removed',
      body: `"${listing.title}" was removed by an administrator.`,
      link: '/dashboard/listings',
    });
  } else {
    await run('DELETE FROM listings WHERE id = ?', [listing.id]);
  }
  res.json({ message: 'Listing deleted.' });
}));

export default router;
