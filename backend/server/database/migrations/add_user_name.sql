-- Add name column to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS name VARCHAR(255);

-- Update existing users with a default name (optional)
UPDATE users SET name = 'User' WHERE name IS NULL;
