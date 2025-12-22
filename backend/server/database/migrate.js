import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import pool from './db.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function runMigrations() {
  try {
    console.log('🔄 Running database migrations...');

    // Create migrations tracking table if not exists
    await pool.query(`
      CREATE TABLE IF NOT EXISTS schema_migrations (
        id SERIAL PRIMARY KEY,
        migration_name VARCHAR(255) UNIQUE NOT NULL,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      );
    `);

    // Get list of migration files
    const migrationsDir = path.join(__dirname, 'migrations');
    const files = fs.readdirSync(migrationsDir)
      .filter(f => f.endsWith('.sql'))
      .sort(); // Run in alphabetical order

    for (const file of files) {
      // Check if migration already applied
      const checkResult = await pool.query(
        'SELECT id FROM schema_migrations WHERE migration_name = $1',
        [file]
      );

      if (checkResult.rows.length > 0) {
        console.log(`  ⏭️  Skipping ${file} (already applied)`);
        continue;
      }

      // Run migration
      console.log(`  ▶️  Running ${file}...`);
      const migrationPath = path.join(migrationsDir, file);
      const sql = fs.readFileSync(migrationPath, 'utf8');
      
      await pool.query(sql);
      
      // Mark as applied
      await pool.query(
        'INSERT INTO schema_migrations (migration_name) VALUES ($1)',
        [file]
      );
      
      console.log(`  ✅ ${file} completed`);
    }

    console.log('✅ All migrations completed successfully!\n');
  } catch (error) {
    console.error('❌ Migration failed:', error.message);
    throw error;
  }
}

export default runMigrations;
