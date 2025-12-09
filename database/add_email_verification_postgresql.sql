-- Script SQL per PostgreSQL - Aggiungi colonne per verifica email
-- Esegui questo script sul tuo database PostgreSQL

ALTER TABLE users 
ADD COLUMN IF NOT EXISTS email_code VARCHAR(6),
ADD COLUMN IF NOT EXISTS email_verified SMALLINT DEFAULT 0,
ADD COLUMN IF NOT EXISTS email_code_expires_at TIMESTAMP;

-- Se la colonna email_verified esiste già come BOOLEAN, convertila a SMALLINT
-- (Esegui solo se hai già creato la colonna come BOOLEAN per errore)
-- ALTER TABLE users 
-- ALTER COLUMN email_verified TYPE SMALLINT USING CASE WHEN email_verified THEN 1 ELSE 0 END;

