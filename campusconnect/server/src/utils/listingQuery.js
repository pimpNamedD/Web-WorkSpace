import { query } from '../db.js';
import { toInt } from './helpers.js';
import { TYPE_KEYS } from './listingTypes.js';

const SORTS = {
  newest: 'l.created_at DESC',
  oldest: 'l.created_at ASC',
  price_asc: 'l.price IS NULL, l.price ASC',
  price_desc: 'l.price DESC',
  popular: 'l.views DESC',
  rating: 'seller_rating IS NULL, seller_rating DESC',
};

/**
 * Builds the WHERE clause for the advanced search / filter engine
 * (1.4 "Search and Filtering", 1.4.1 "Advanced Search Filters").
 * Every module shares this so a single implementation serves all six.
 */
export function buildFilters(params, { includeAllStatuses = false } = {}) {
  const where = [];
  const values = [];

  if (params.type && TYPE_KEYS.includes(params.type)) {
    where.push('l.type = ?');
    values.push(params.type);
  } else if (params.types) {
    const list = String(params.types).split(',').filter((t) => TYPE_KEYS.includes(t));
    if (list.length) {
      where.push(`l.type IN (${list.map(() => '?').join(',')})`);
      values.push(...list);
    }
  }

  if (params.status) {
    where.push('l.status = ?');
    values.push(params.status);
  } else if (!includeAllStatuses) {
    where.push("l.status = 'active'");
  } else {
    where.push("l.status <> 'removed'");
  }

  if (params.q) {
    const term = `%${String(params.q).trim()}%`;
    where.push(`(l.title LIKE ? OR l.description LIKE ? OR l.category LIKE ?
                 OR l.location LIKE ? OR l.subject LIKE ? OR l.company LIKE ?)`);
    values.push(term, term, term, term, term, term);
  }

  if (params.category) {
    where.push('l.category = ?');
    values.push(params.category);
  }
  if (params.location) {
    where.push('l.location LIKE ?');
    values.push(`%${params.location}%`);
  }
  if (params.min_price !== undefined && params.min_price !== '') {
    where.push('(l.price >= ? OR l.budget_max >= ?)');
    values.push(Number(params.min_price), Number(params.min_price));
  }
  if (params.max_price !== undefined && params.max_price !== '') {
    where.push('(l.price <= ? OR l.budget_min <= ?)');
    values.push(Number(params.max_price), Number(params.max_price));
  }
  if (params.condition) {
    where.push('l.item_condition = ?');
    values.push(params.condition);
  }
  if (params.bedrooms) {
    where.push('l.bedrooms >= ?');
    values.push(Number(params.bedrooms));
  }
  if (params.furnished !== undefined && params.furnished !== '') {
    where.push('l.furnished = ?');
    values.push(['1', 'true', 'yes'].includes(String(params.furnished).toLowerCase()) ? 1 : 0);
  }
  if (params.subject) {
    where.push('(l.subject LIKE ? OR l.category = ?)');
    values.push(`%${params.subject}%`, params.subject);
  }
  if (params.level) {
    where.push('l.level = ?');
    values.push(params.level);
  }
  if (params.job_type) {
    where.push('l.job_type = ?');
    values.push(params.job_type);
  }
  if (params.gender_pref) {
    where.push('l.gender_pref = ?');
    values.push(params.gender_pref);
  }
  if (params.user_id) {
    where.push('l.user_id = ?');
    values.push(Number(params.user_id));
  }
  if (params.posted_within) {
    where.push('l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)');
    values.push(toInt(params.posted_within, 30, { min: 1, max: 365 }));
  }
  if (params.min_rating) {
    where.push('COALESCE((SELECT AVG(rating) FROM reviews r WHERE r.reviewee_id = l.user_id), 0) >= ?');
    values.push(Number(params.min_rating));
  }
  if (['1', 'true'].includes(String(params.verified_only).toLowerCase())) {
    where.push("u.verification_status = 'verified'");
  }

  return { where, values };
}

/**
 * Runs a filtered, paginated listing search and returns rows plus a total.
 * `viewerId` (optional) marks which results the current user has favourited.
 */
export async function searchListings(params, viewerId = null, options = {}) {
  const { where, values } = buildFilters(params, options);
  const page = toInt(params.page, 1, { min: 1, max: 10000 });
  const limit = toInt(params.limit, 12, { min: 1, max: 60 });
  const offset = (page - 1) * limit;
  const orderBy = SORTS[params.sort] ?? SORTS.newest;
  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';

  const rows = await query(
    `SELECT l.*,
            u.full_name AS seller_name, u.avatar_url AS seller_avatar,
            u.verification_status AS seller_verification, u.university AS seller_university,
            (SELECT ROUND(AVG(rating), 2) FROM reviews r WHERE r.reviewee_id = l.user_id) AS seller_rating,
            (SELECT COUNT(*) FROM reviews r WHERE r.reviewee_id = l.user_id) AS seller_review_count,
            (SELECT url FROM listing_images i WHERE i.listing_id = l.id ORDER BY i.sort_order LIMIT 1) AS image,
            (SELECT COUNT(*) FROM favorites f WHERE f.listing_id = l.id) AS favorite_count,
            ${viewerId ? '(SELECT COUNT(*) FROM favorites f2 WHERE f2.listing_id = l.id AND f2.user_id = ?)' : '0'} AS is_favorite
       FROM listings l
       JOIN users u ON u.id = l.user_id
       ${whereSql}
      ORDER BY ${orderBy}
      LIMIT ${limit} OFFSET ${offset}`,
    viewerId ? [viewerId, ...values] : values,
  );

  const [{ total }] = await query(
    `SELECT COUNT(*) AS total FROM listings l JOIN users u ON u.id = l.user_id ${whereSql}`,
    values,
  );

  return {
    listings: rows.map((r) => ({ ...r, is_favorite: Number(r.is_favorite) > 0 })),
    pagination: { page, limit, total: Number(total), pages: Math.max(1, Math.ceil(Number(total) / limit)) },
  };
}
