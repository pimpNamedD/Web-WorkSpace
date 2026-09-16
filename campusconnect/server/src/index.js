import express from 'express';
import cors from 'cors';
import morgan from 'morgan';
import multer from 'multer';
import { config } from './config.js';
import { pool } from './db.js';
import { attachUser } from './middleware/auth.js';
import { startMaintenance } from './jobs/maintenance.js';
import { HttpError } from './utils/helpers.js';

import authRoutes from './routes/auth.js';
import userRoutes from './routes/users.js';
import listingRoutes from './routes/listings.js';
import applicationRoutes from './routes/applications.js';
import reviewRoutes from './routes/reviews.js';
import reportRoutes from './routes/reports.js';
import messageRoutes from './routes/messages.js';
import notificationRoutes from './routes/notifications.js';
import favoriteRoutes from './routes/favorites.js';
import savedSearchRoutes from './routes/savedSearches.js';
import adminRoutes from './routes/admin.js';
import metaRoutes from './routes/meta.js';

const app = express();

app.use(cors({ origin: [config.clientOrigin, 'http://localhost:5173', 'http://127.0.0.1:5173'], credentials: true }));
app.use(express.json({ limit: '2mb' }));
app.use(express.urlencoded({ extended: true }));
app.use(morgan('dev'));
app.use('/uploads', express.static(config.uploadsDir, { maxAge: '7d' }));
app.use(attachUser);

app.get('/api/health', async (_req, res) => {
  try {
    await pool.query('SELECT 1');
    res.json({ status: 'ok', database: 'connected', time: new Date().toISOString() });
  } catch (err) {
    res.status(503).json({ status: 'degraded', database: 'unreachable', error: err.message });
  }
});

app.use('/api/auth', authRoutes);
app.use('/api/users', userRoutes);
app.use('/api/listings', listingRoutes);
app.use('/api/applications', applicationRoutes);
app.use('/api/reviews', reviewRoutes);
app.use('/api/reports', reportRoutes);
app.use('/api/messages', messageRoutes);
app.use('/api/notifications', notificationRoutes);
app.use('/api/favorites', favoriteRoutes);
app.use('/api/saved-searches', savedSearchRoutes);
app.use('/api/admin', adminRoutes);
app.use('/api', metaRoutes);

app.use('/api', (_req, res) => res.status(404).json({ error: 'That API endpoint does not exist.' }));

// Central error handler - always answers with JSON the client can display.
app.use((err, _req, res, _next) => {
  if (err instanceof multer.MulterError) {
    const message = err.code === 'LIMIT_FILE_SIZE'
      ? 'Each image must be smaller than 4 MB.'
      : 'Could not upload that file.';
    return res.status(400).json({ error: message });
  }
  if (err instanceof HttpError) {
    return res.status(err.status).json({ error: err.message, details: err.details ?? undefined });
  }
  if (err?.code === 'ER_DUP_ENTRY') {
    return res.status(409).json({ error: 'That record already exists.' });
  }
  console.error('[error]', err);
  return res.status(500).json({ error: 'Something went wrong on the server.' });
});

const server = app.listen(config.port, () => {
  console.log(`Campus Connect API listening on http://localhost:${config.port}`);
  console.log(`Database: ${config.db.database} @ ${config.db.host}:${config.db.port}`);
  startMaintenance();
});

const shutdown = async () => {
  server.close();
  await pool.end().catch(() => {});
  process.exit(0);
};
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

export default app;
