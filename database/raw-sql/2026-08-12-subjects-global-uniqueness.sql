-- Schema change: enforce subject_code/title uniqueness campus-wide, not
-- just per program, at the database level (previously app-validation-only
-- via SubjectController's Rule::unique — see git history).
-- Applied: 2026-08-12, approved by Rico
-- Ran directly against syllabihub_db (raw SQL, no Laravel migrations —
-- see CLAUDE.md). Recorded for the database team (Mary, Vincent) and for
-- anyone re-provisioning the DB from scratch.
--
-- Rico, 2026-08-12: a curriculum update is modeled as retiring (soft-
-- deleting) the old subject row, not two rows sharing a code/title side
-- by side — so subject_code and title must each be unique across ALL
-- programs (BSIT+DIT), not just within one. App-level validation already
-- enforced this, but without a DB constraint a race between two
-- simultaneous submits could still slip a duplicate through.
--
-- A plain UNIQUE(subject_code) can't be used directly, though: it would
-- also block reusing a code/title after its row is soft-deleted, which
-- is intentionally allowed (a retired code frees up for reuse). MySQL
-- unique indexes treat NULL as "not equal to any other NULL", so the
-- fix is a generated column that is NULL whenever the row is soft-
-- deleted and the real value otherwise — soft-deleted rows are then
-- invisible to the uniqueness check, and only live rows are compared.

-- 1. program_id's FK (subjects_ibfk_1) needs its own index once
--    uq_program_subject below is dropped — that composite index was the
--    only thing currently satisfying it.
ALTER TABLE subjects
  ADD INDEX idx_subjects_program_id (program_id);

-- 2. Drop the old per-program composite unique constraint.
ALTER TABLE subjects
  DROP INDEX uq_program_subject;

-- 3. Soft-delete-aware generated columns + the real unique constraints.
ALTER TABLE subjects
  ADD COLUMN subject_code_active VARCHAR(20)
    GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN subject_code END) VIRTUAL
    AFTER subject_code,
  ADD COLUMN title_active VARCHAR(255)
    GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN title END) VIRTUAL
    AFTER title;

ALTER TABLE subjects
  ADD UNIQUE INDEX uq_subject_code_active (subject_code_active),
  ADD UNIQUE INDEX uq_title_active (title_active);
