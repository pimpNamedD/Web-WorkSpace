import { one, query, run } from '../db.js';
import { buildFilters } from '../utils/listingQuery.js';
import { notify } from '../utils/notify.js';

/**
 * Automated notifications for saved searches (1.4.1). When a listing is
 * published we re-run every subscribed saved search restricted to that one
 * listing; if it still matches, the owner is alerted.
 */
export async function runSavedSearchAlerts(listingId) {
  const listing = await one('SELECT * FROM listings WHERE id = ? AND status = ?', [listingId, 'active']);
  if (!listing) return 0;

  const searches = await query(
    'SELECT s.*, u.id AS owner_id FROM saved_searches s JOIN users u ON u.id = s.user_id WHERE s.notify = 1 AND u.is_suspended = 0',
  );

  let alerted = 0;
  for (const search of searches) {
    if (search.user_id === listing.user_id) continue;   // never alert on your own post
    let params;
    try {
      params = JSON.parse(search.query_json);
    } catch {
      continue;
    }
    if (search.type) params.type = search.type;

    const { where, values } = buildFilters(params);
    const sql = `SELECT l.id FROM listings l JOIN users u ON u.id = l.user_id
                  WHERE l.id = ?${where.length ? ' AND ' + where.join(' AND ') : ''} LIMIT 1`;
    const match = await one(sql, [listing.id, ...values]);
    if (!match) continue;

    await notify(search.user_id, {
      type: 'saved_search_match',
      title: `New match for "${search.name}"`,
      body: `${listing.title} was just posted and matches your saved search.`,
      link: `/listing/${listing.id}`,
    });
    await run('UPDATE saved_searches SET last_alerted_at = NOW() WHERE id = ?', [search.id]);
    alerted += 1;
  }
  return alerted;
}
