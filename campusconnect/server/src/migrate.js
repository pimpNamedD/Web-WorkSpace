/**
 * Creates the database (if absent) and applies schema.sql.
 *   node src/migrate.js            -> create/update tables
 *   node src/migrate.js --fresh    -> drop the database first, then recreate
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import mysql from 'mysql2/promise';
import { config } from './config.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fresh = process.argv.includes('--fresh');

async function main() {
  const admin = await mysql.createConnection({
    host: config.db.host,
    port: config.db.port,
    user: config.db.user,
    password: config.db.password,
    multipleStatements: true,
  });

  if (fresh) {
    await admin.query(`DROP DATABASE IF EXISTS \`${config.db.database}\``);
    console.log(`Dropped database ${config.db.database}`);
  }

  await admin.query(
    `CREATE DATABASE IF NOT EXISTS \`${config.db.database}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci`,
  );
  await admin.query(`USE \`${config.db.database}\``);

  const sql = fs.readFileSync(path.join(__dirname, 'schema.sql'), 'utf8');
  await admin.query(sql);

  const [tables] = await admin.query('SHOW TABLES');
  console.log(`Database "${config.db.database}" ready with ${tables.length} tables:`);
  console.log('  ' + tables.map((t) => Object.values(t)[0]).join(', '));

  await admin.end();
}

main().catch((err) => {
  console.error('Migration failed:', err.message);
  process.exit(1);
});
