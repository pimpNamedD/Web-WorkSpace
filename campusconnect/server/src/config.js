import dotenv from 'dotenv';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.join(__dirname, '..', '.env') });

const num = (v, d) => (v === undefined || v === '' ? d : Number(v));
const bool = (v, d) => (v === undefined || v === '' ? d : String(v).toLowerCase() === 'true');

export const config = {
  port: num(process.env.PORT, 5050),
  clientOrigin: process.env.CLIENT_ORIGIN || 'http://localhost:5173',
  uploadsDir: path.join(__dirname, '..', 'uploads'),

  db: {
    host: process.env.DB_HOST || 'localhost',
    port: num(process.env.DB_PORT, 3306),
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'campusconnect_db',
  },

  jwt: {
    secret: process.env.JWT_SECRET || 'campus_connect_dev_secret_change_me',
    expiresIn: process.env.JWT_EXPIRES_IN || '7d',
  },

  // Student verification (1.4.2 "Student Verification"). An account whose
  // e-mail matches one of these domains is auto-verified on registration;
  // everyone else is queued for manual approval by an administrator.
  verification: {
    autoDomains: (process.env.AUTO_VERIFY_DOMAINS || 'unilus.ac.zm,student.unilus.ac.zm')
      .split(',')
      .map((d) => d.trim().toLowerCase())
      .filter(Boolean),
    requireVerifiedToPost: bool(process.env.REQUIRE_VERIFIED_TO_POST, true),
  },

  // Listing expiry & auto-archive (1.4.1)
  listings: {
    defaultTtlDays: num(process.env.LISTING_TTL_DAYS, 60),
    maintenanceIntervalMinutes: num(process.env.MAINTENANCE_INTERVAL_MINUTES, 30),
  },

  mail: {
    enabled: bool(process.env.MAIL_ENABLED, false),
    host: process.env.SMTP_HOST || '',
    port: num(process.env.SMTP_PORT, 587),
    user: process.env.SMTP_USER || '',
    pass: process.env.SMTP_PASS || '',
    from: process.env.MAIL_FROM || 'Campus Connect <no-reply@campusconnect.local>',
  },
};
