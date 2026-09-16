/** Thrown deliberately by route handlers; the error middleware turns it into JSON. */
export class HttpError extends Error {
  constructor(status, message, details = null) {
    super(message);
    this.status = status;
    this.details = details;
  }
}

/** Wrap an async route handler so rejected promises reach the error middleware. */
export const asyncHandler = (fn) => (req, res, next) =>
  Promise.resolve(fn(req, res, next)).catch(next);

/** Strip password hashes and other internals before sending a user to the client. */
export function publicUser(row) {
  if (!row) return null;
  const {
    password_hash, verification_note, suspension_reason, email_notifications, ...rest
  } = row;
  return rest;
}

/** The full record for the account owner / an administrator. */
export function selfUser(row) {
  if (!row) return null;
  const { password_hash, ...rest } = row;
  return rest;
}

/** Coerce a query-string value to a positive integer within bounds. */
export function toInt(value, fallback, { min = 0, max = Number.MAX_SAFE_INTEGER } = {}) {
  const n = Number.parseInt(value, 10);
  if (Number.isNaN(n)) return fallback;
  return Math.min(Math.max(n, min), max);
}

/** Undefined/'' -> null, so optional fields can be passed straight to MySQL. */
export const nz = (v) => (v === undefined || v === '' || v === null ? null : v);

/** Optional numeric field -> number or null. */
export const nzNum = (v) => {
  if (v === undefined || v === '' || v === null) return null;
  const n = Number(v);
  return Number.isNaN(n) ? null : n;
};

/** Optional boolean field -> 1/0 or null. */
export const nzBool = (v) => {
  if (v === undefined || v === '' || v === null) return null;
  if (typeof v === 'boolean') return v ? 1 : 0;
  return ['1', 'true', 'yes', 'on'].includes(String(v).toLowerCase()) ? 1 : 0;
};

/** Deterministic conversation key so both participants land in the same thread. */
export function threadKey(listingId, userA, userB) {
  const [lo, hi] = [Number(userA), Number(userB)].sort((a, b) => a - b);
  return `${listingId ?? 0}:${lo}:${hi}`;
}

export function requireFields(body, fields) {
  const missing = fields.filter((f) => body[f] === undefined || body[f] === null || body[f] === '');
  if (missing.length) {
    throw new HttpError(400, `Missing required field(s): ${missing.join(', ')}`, { missing });
  }
}
