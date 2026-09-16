import { config } from '../config.js';
import { query, run } from '../db.js';
import { notify } from '../utils/notify.js';

/**
 * Listing expiry and auto-archive (1.4.1). Active listings past their
 * expires_at date are archived and the owner is invited to renew, so the
 * platform never fills up with stale posts.
 */
export async function archiveExpiredListings() {
  const due = await query(
    "SELECT id, user_id, title FROM listings WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at < NOW()",
  );
  if (!due.length) return 0;

  const ids = due.map((l) => l.id);
  await run(`UPDATE listings SET status = 'archived' WHERE id IN (${ids.map(() => '?').join(',')})`, ids);

  for (const listing of due) {
    await notify(listing.user_id, {
      type: 'listing_expired',
      title: 'A listing has expired',
      body: `"${listing.title}" has been archived. Renew it from your dashboard to make it visible again.`,
      link: '/dashboard/listings',
    });
  }
  console.log(`[maintenance] archived ${due.length} expired listing(s)`);
  return due.length;
}

/** Warn owners three days before a listing expires. */
export async function warnExpiringListings() {
  const soon = await query(
    `SELECT id, user_id, title FROM listings
      WHERE status = 'active' AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
        AND NOT EXISTS (
          SELECT 1 FROM notifications n
           WHERE n.user_id = listings.user_id AND n.type = 'listing_expiring'
             AND n.link = CONCAT('/listing/', listings.id)
             AND n.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY))`,
  );
  for (const listing of soon) {
    await notify(listing.user_id, {
      type: 'listing_expiring',
      title: 'A listing is about to expire',
      body: `"${listing.title}" expires within three days. Renew it to keep it live.`,
      link: `/listing/${listing.id}`,
    });
  }
  return soon.length;
}

let timer = null;

/** Starts the background maintenance loop. */
export function startMaintenance() {
  const runAll = async () => {
    try {
      await archiveExpiredListings();
      await warnExpiringListings();
    } catch (err) {
      console.error('[maintenance] failed:', err.message);
    }
  };
  runAll();
  timer = setInterval(runAll, config.listings.maintenanceIntervalMinutes * 60 * 1000);
  timer.unref?.();
  return timer;
}

export function stopMaintenance() {
  if (timer) clearInterval(timer);
  timer = null;
}
