-- Agregar columna imagen a la tabla posts si no existe
ALTER TABLE posts ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) NULL AFTER contenido; 