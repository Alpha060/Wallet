import pg from 'pg';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import dotenv from 'dotenv';

dotenv.config();

const { Pool } = pg;
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  max: 10,
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 2000,
});

async function runMigration(filename) {
  try {
    console.log(`Running migration: ${filename}`);
    
    const migrationPath = path.join(__dirname, filename);
    const sql = fs.readFileSync(migrationPath, 'utf8');
    
    await pool.query(sql);
    
    console.log(`✓ Migration ${filename} completed successfully`);
  } catch (error) {
    console.error(`✗ Migration ${filename} failed:`, error.message);
    throw error;
  }
}

async function main() {
  const migrationFile = process.argv[2];
  
  if (!migrationFile) {
    console.error('Usage: node runMigration.js <migration-file.sql>');
    process.exit(1);
  }
  
  try {
    await runMigration(migrationFile);
    console.log('\n✓ All migrations completed successfully');
    await pool.end();
    process.exit(0);
  } catch (error) {
    console.error('\n✗ Migration failed');
    await pool.end();
    process.exit(1);
  }
}

main();
