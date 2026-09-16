import { one, run } from '../db.js';
import { sendMail } from './mailer.js';

/**
 * Create an in-app notification and, when the recipient has e-mail alerts
 * switched on, mirror it to their inbox (1.4 "Notification System").
 */
export async function notify(userId, { type, title, body = null, link = null, email = true }) {
  if (!userId) return null;
  const result = await run(
    'INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)',
    [userId, type, title, body, link],
  );

  if (email) {
    const user = await one('SELECT email, full_name, email_notifications FROM users WHERE id = ?', [userId]);
    if (user?.email_notifications) {
      await sendMail({
        to: user.email,
        subject: `Campus Connect: ${title}`,
        text: `Hi ${user.full_name},\n\n${title}\n${body ?? ''}\n\nOpen Campus Connect to view the details.`,
      });
    }
  }
  return result.insertId;
}

/** Send the same notification to many users (used by saved-search alerts). */
export async function notifyMany(userIds, payload) {
  const unique = [...new Set(userIds.filter(Boolean))];
  await Promise.all(unique.map((id) => notify(id, payload)));
  return unique.length;
}
