import { Router } from 'express';
import { one, query, run } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { asyncHandler, HttpError, nz, requireFields } from '../utils/helpers.js';
import { searchListings } from '../utils/listingQuery.js';

const router = Router();

const FILTER_KEYS = [
  'q', 'type', 'category', 'location', 'min_price', 'max_price', 'condition', 'bedrooms',
  'furnished', 'subject', 'level', 'job_type', 'gender_pref', 'min_rating', 'verified_only', 'sort',
];

/** Keep only recognised filter keys so stored searches stay valid. */
function cleanQuery(input = {}) {
  const out = {};
  for (const key of FILTER_KEYS) {
    if (input[key] !== undefined && input[key] !== '' && input[key] !== null) out[key] = input[key];
  }
  return out;
}

/** GET /api/saved-searches */
router.get('/', requireAuth, asyncHandler(async (req, res) => {
  const rows = await query('SELECT * FROM saved_searches WHERE user_id = ? ORDER BY created_at DESC', [req.user.id]);
  res.json({
    saved_searches: rows.map((r) => ({ ...r, query: JSON.parse(r.query_json), query_json: undefined })),
  });
}));

/** POST /api/saved-searches - store a filter set and subscribe to alerts. */
router.post('/', requireAuth, asyncHandler(async (req, res) => {
  requireFields(req.body, ['name']);
  const filters = cleanQuery(req.body.query ?? req.body.filters ?? {});
  if (!Object.keys(filters).length) throw new HttpError(400, 'Add at least one filter before saving this search.');

  const count = await one('SELECT COUNT(*) AS c FROM saved_searches WHERE user_id = ?', [req.user.id]);
  if (Number(count.c) >= 20) throw new HttpError(400, 'You can keep up to 20 saved searches. Delete one first.');

  const result = await run(
    'INSERT INTO saved_searches (user_id, name, type, query_json, notify) VALUES (?, ?, ?, ?, ?)',
    [req.user.id, String(req.body.name).trim(), nz(req.body.type ?? filters.type), JSON.stringify(filters), req.body.notify === false ? 0 : 1],
  );
  const row = await one('SELECT * FROM saved_searches WHERE id = ?', [result.insertId]);
  res.status(201).json({ saved_search: { ...row, query: JSON.parse(row.query_json), query_json: undefined } });
}));

/** GET /api/saved-searches/:id/run - re-run a saved search. */
router.get('/:id/run', requireAuth, asyncHandler(async (req, res) => {
  const row = await one('SELECT * FROM saved_searches WHERE id = ? AND user_id = ?', [req.params.id, req.user.id]);
  if (!row) throw new HttpError(404, 'That saved search does not exist.');
  const filters = { ...JSON.parse(row.query_json), page: req.query.page, limit: req.query.limit };
  if (row.type) filters.type = row.type;
  res.json({ saved_search: { id: row.id, name: row.name }, ...(await searchListings(filters, req.user.id)) });
}));

/** PATCH /api/saved-searches/:id - rename or mute alerts. */
router.patch('/:id', requireAuth, asyncHandler(async (req, res) => {
  const row = await one('SELECT * FROM saved_searches WHERE id = ? AND user_id = ?', [req.params.id, req.user.id]);
  if (!row) throw new HttpError(404, 'That saved search does not exist.');

  const sets = [];
  const params = [];
  if (req.body.name !== undefined) { sets.push('name = ?'); params.push(String(req.body.name).trim()); }
  if (req.body.notify !== undefined) { sets.push('notify = ?'); params.push(req.body.notify ? 1 : 0); }
  if (req.body.query !== undefined) { sets.push('query_json = ?'); params.push(JSON.stringify(cleanQuery(req.body.query))); }
  if (!sets.length) throw new HttpError(400, 'Nothing to update.');

  params.push(row.id);
  await run(`UPDATE saved_searches SET ${sets.join(', ')} WHERE id = ?`, params);
  const updated = await one('SELECT * FROM saved_searches WHERE id = ?', [row.id]);
  res.json({ saved_search: { ...updated, query: JSON.parse(updated.query_json), query_json: undefined } });
}));

/** DELETE /api/saved-searches/:id */
router.delete('/:id', requireAuth, asyncHandler(async (req, res) => {
  await run('DELETE FROM saved_searches WHERE id = ? AND user_id = ?', [req.params.id, req.user.id]);
  res.json({ message: 'Saved search deleted.' });
}));

export default router;
