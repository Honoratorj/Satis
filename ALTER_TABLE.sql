USE CMMJ_TI;
ALTER TABLE pesquisa_satisfacao
    ADD COLUMN IF NOT EXISTS ticket_name VARCHAR(255) NULL AFTER ticket_id,
    ADD COLUMN IF NOT EXISTS ticket_createdate DATETIME NULL AFTER ticket_name,
    ADD COLUMN IF NOT EXISTS ticket_solvedate DATETIME NULL AFTER ticket_createdate,
    ADD COLUMN IF NOT EXISTS comentario TEXT NULL AFTER avaliacao,
    ADD COLUMN IF NOT EXISTS tecnico VARCHAR(255) NULL AFTER comentario,
    ADD COLUMN IF NOT EXISTS cargo VARCHAR(255) NULL AFTER tecnico,
    ADD COLUMN IF NOT EXISTS foto_tecnico VARCHAR(500) NULL AFTER cargo;
CREATE INDEX IF NOT EXISTS idx_pesquisa_ticket_id ON pesquisa_satisfacao (ticket_id);
CREATE INDEX IF NOT EXISTS idx_pesquisa_tecnico ON pesquisa_satisfacao (tecnico);
CREATE INDEX IF NOT EXISTS idx_pesquisa_created_at ON pesquisa_satisfacao (created_at);
