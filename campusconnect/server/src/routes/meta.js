import { Router } from 'express';
import { one, query } from '../db.js';
import { asyncHandler, toInt } from '../utils/helpers.js';
import { searchListings } from '../utils/listingQuery.js';
import {
  LISTING_TYPES, CONDITIONS, LEVELS, JOB_TYPES, GENDER_PREFS,
  PRICE_UNITS, REPORT_REASONS, UNIVERSITIES,
} from '../utils/listingTypes.js';

const router = Router();

/**
 * GET /api/meta - every option list the client needs to build its dynamic
 * forms and filter panels, served from one place so the two never diverge.
 */
router.get('/meta', asyncHandler(async (_req, res) => {
  const locations = await query(
    `SELECT location, COUNT(*) AS count FROM listings
      WHERE status = 'active' AND location IS NOT NULL AND location <> ''
      GROUP BY location ORDER BY count DESC LIMIT 40`,
  );
  res.json({
    listing_types: LISTING_TYPES,
    conditions: CONDITIONS,
    levels: LEVELS,
    job_types: JOB_TYPES,
    gender_prefs: GENDER_PREFS,
    price_units: PRICE_UNITS,
    report_reasons: REPORT_REASONS,
    universities: UNIVERSITIES,
    locations: locations.map((l) => l.location),
  });
}));

/**
 * GET /api/search - global keyword search across every module at once
 * (1.5 "Search and Filtering").
 */
router.get('/search', asyncHandler(async (req, res) => {
  const result = await searchListings(req.query, req.user?.id ?? null);
  const byType = await query(
    `SELECT type, COUNT(*) AS count FROM listings
      WHERE status = 'active' AND (title LIKE ? OR description LIKE ? OR category LIKE ? OR location LIKE ?)
      GROUP BY type`,
    Array(4).fill(`%${req.query.q ?? ''}%`),
  );
  res.json({ ...result, counts_by_type: byType });
}));

/** GET /api/stats - public headline numbers shown on the landing page. */
router.get('/stats', asyncHandler(async (_req, res) => {
  const totals = await one(
    `SELECT (SELECT COUNT(*) FROM listings WHERE status = 'active') AS active_listings,
            (SELECT COUNT(*) FROM users WHERE verification_status = 'verified') AS verified_students,
            (SELECT COUNT(*) FROM listings) AS total_listings,
            (SELECT COUNT(*) FROM reviews) AS reviews`,
  );
  const byType = await query(
    "SELECT type, COUNT(*) AS count FROM listings WHERE status = 'active' GROUP BY type",
  );
  res.json({ totals, by_type: Object.fromEntries(byType.map((r) => [r.type, Number(r.count)])) });
}));

/** GET /api/trending - most viewed active listings this fortnight. */
router.get('/trending', asyncHandler(async (req, res) => {
  const limit = toInt(req.query.limit, 6, { min: 1, max: 20 });
  const { listings } = await searchListings(
    { sort: 'popular', limit, posted_within: 30 },
    req.user?.id ?? null,
  );
  res.json({ listings });
}));

export default router;
