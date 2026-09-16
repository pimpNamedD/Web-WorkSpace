import jwt from 'jsonwebtoken';
import { config } from '../config.js';
import { one } from '../db.js';
import { HttpError } from '../utils/helpers.js';

export function signToken(user) {
  return jwt.sign(
    { sub: user.id, role: user.role, email: user.email },
    config.jwt.secret,
    { expiresIn: config.jwt.expiresIn },
  );
}

function readToken(req) {
  const header = req.headers.authorization || '';
  if (header.startsWith('Bearer ')) return header.slice(7).trim();
  return null;
}

/** Populates req.user when a valid token is present; never rejects. */
export async function attachUser(req, _res, next) {
  const token = readToken(req);
  if (!token) return next();
  try {
    const payload = jwt.verify(token, config.jwt.secret);
    const user = await one('SELECT * FROM users WHERE id = ?', [payload.sub]);
    if (user) req.user = user;
  } catch {
    /* invalid or expired token - treated as anonymous */
  }
  return next();
}

/** Requires a signed-in, non-suspended account. */
export function requireAuth(req, _res, next) {
  if (!req.user) return next(new HttpError(401, 'Sign in to continue.'));
  if (req.user.is_suspended) {
    return next(new HttpError(403, `Your account has been suspended. ${req.user.suspension_reason ?? ''}`.trim()));
  }
  return next();
}

/** Requires one of the given roles. */
export const requireRole = (...roles) => (req, _res, next) => {
  if (!req.user) return next(new HttpError(401, 'Sign in to continue.'));
  if (!roles.includes(req.user.role)) return next(new HttpError(403, 'You do not have permission to do that.'));
  return next();
};

export const requireAdmin = requireRole('admin');

/**
 * Only verified students (or employers/admins) may publish listings - this is
 * the enforcement point for the student verification feature (1.4.2).
 */
export function requireVerified(req, _res, next) {
  if (!req.user) return next(new HttpError(401, 'Sign in to continue.'));
  if (!config.verification.requireVerifiedToPost) return next();
  if (req.user.role === 'admin') return next();
  if (req.user.verification_status !== 'verified') {
    return next(new HttpError(403, 'Your student account must be verified before you can post. Submit your details under Profile > Verification.'));
  }
  return next();
}
