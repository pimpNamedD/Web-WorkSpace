import { Router } from 'express';
import bcrypt from 'bcryptjs';
import rateLimit from 'express-rate-limit';
import { config } from '../config.js';
import { one, run } from '../db.js';
import { signToken, requireAuth } from '../middleware/auth.js';
import { asyncHandler, HttpError, selfUser, requireFields } from '../utils/helpers.js';
import { notify } from '../utils/notify.js';

const router = Router();

const authLimiter = rateLimit({
  windowMs: 10 * 60 * 1000,
  max: 40,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'Too many attempts. Please try again in a few minutes.' },
});

/** A university e-mail domain earns instant verification (1.4.2). */
function domainVerified(email) {
  const domain = String(email).split('@')[1]?.toLowerCase() ?? '';
  return config.verification.autoDomains.some((d) => domain === d || domain.endsWith(`.${d}`));
}

/**
 * POST /api/auth/register
 * Creates an account. Students supplying a recognised university e-mail are
 * verified immediately; everyone else enters the administrator queue.
 */
router.post('/register', authLimiter, asyncHandler(async (req, res) => {
  requireFields(req.body, ['full_name', 'email', 'password']);
  const {
    full_name, email, password, role = 'student',
    student_id, university, program, year_of_study, phone,
  } = req.body;

  if (String(password).length < 6) throw new HttpError(400, 'Password must be at least 6 characters.');
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) throw new HttpError(400, 'Enter a valid e-mail address.');
  if (!['student', 'employer'].includes(role)) throw new HttpError(400, 'Role must be student or employer.');
  if (role === 'student' && !student_id) throw new HttpError(400, 'Student number is required for a student account.');

  const existing = await one('SELECT id FROM users WHERE email = ?', [email.toLowerCase()]);
  if (existing) throw new HttpError(409, 'An account with that e-mail already exists.');

  const auto = role === 'student' && domainVerified(email);
  const status = role === 'student' ? (auto ? 'verified' : 'unverified') : 'verified';

  const result = await run(
    `INSERT INTO users
       (full_name, email, password_hash, role, student_id, university, program, year_of_study, phone,
        verification_status, verified_at, verification_note)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      full_name.trim(), email.toLowerCase(), await bcrypt.hash(password, 10), role,
      student_id ?? null, university ?? null, program ?? null, year_of_study ?? null, phone ?? null,
      status, status === 'verified' ? new Date() : null,
      auto ? 'Auto-verified from university e-mail domain.' : null,
    ],
  );

  const user = await one('SELECT * FROM users WHERE id = ?', [result.insertId]);
  await notify(user.id, {
    type: 'welcome',
    title: 'Welcome to Campus Connect',
    body: auto
      ? 'Your student status was verified automatically from your university e-mail. You can start posting right away.'
      : 'Submit your student details under Profile > Verification so an administrator can verify your account.',
    link: '/settings',
    email: false,
  });

  res.status(201).json({ token: signToken(user), user: selfUser(user) });
}));

/** POST /api/auth/login */
router.post('/login', authLimiter, asyncHandler(async (req, res) => {
  requireFields(req.body, ['email', 'password']);
  const user = await one('SELECT * FROM users WHERE email = ?', [String(req.body.email).toLowerCase()]);
  if (!user || !(await bcrypt.compare(req.body.password, user.password_hash))) {
    throw new HttpError(401, 'Incorrect e-mail or password.');
  }
  if (user.is_suspended) {
    throw new HttpError(403, `Your account has been suspended. ${user.suspension_reason ?? ''}`.trim());
  }
  await run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [user.id]);
  res.json({ token: signToken(user), user: selfUser(user) });
}));

/** GET /api/auth/me - current session */
router.get('/me', requireAuth, asyncHandler(async (req, res) => {
  res.json({ user: selfUser(req.user) });
}));

/** POST /api/auth/change-password */
router.post('/change-password', requireAuth, asyncHandler(async (req, res) => {
  requireFields(req.body, ['current_password', 'new_password']);
  const ok = await bcrypt.compare(req.body.current_password, req.user.password_hash);
  if (!ok) throw new HttpError(400, 'Your current password is incorrect.');
  if (String(req.body.new_password).length < 6) throw new HttpError(400, 'New password must be at least 6 characters.');
  await run('UPDATE users SET password_hash = ? WHERE id = ?', [
    await bcrypt.hash(req.body.new_password, 10), req.user.id,
  ]);
  res.json({ message: 'Password updated.' });
}));

export default router;
