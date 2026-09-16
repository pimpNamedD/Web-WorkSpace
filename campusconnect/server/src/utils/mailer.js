import nodemailer from 'nodemailer';
import { config } from '../config.js';

let transporter = null;

if (config.mail.enabled && config.mail.host) {
  transporter = nodemailer.createTransport({
    host: config.mail.host,
    port: config.mail.port,
    secure: config.mail.port === 465,
    auth: config.mail.user ? { user: config.mail.user, pass: config.mail.pass } : undefined,
  });
}

/**
 * Automated e-mail notifications (1.4.1). When SMTP is not configured the
 * message is logged instead, so the system runs fine offline / in the lab.
 */
export async function sendMail({ to, subject, text }) {
  if (!transporter) {
    console.log(`[mail:disabled] would send to ${to}: ${subject}`);
    return { delivered: false, reason: 'mail_disabled' };
  }
  try {
    await transporter.sendMail({ from: config.mail.from, to, subject, text });
    return { delivered: true };
  } catch (err) {
    console.error('[mail:error]', err.message);
    return { delivered: false, reason: err.message };
  }
}
