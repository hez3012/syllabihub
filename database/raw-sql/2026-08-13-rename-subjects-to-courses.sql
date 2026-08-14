-- ============================================================
-- Rename "Subject" terminology to "Course" throughout the schema
-- (2026-08-13, per Rico / supervisor request — Option A: full
-- technical rename, not just display wording)
--
-- subjects -> courses (+ subject_code -> course_code, and the
-- generated/uniqueness columns + indexes that depend on it)
-- syllabi.subject_id -> course_id
-- subject_change_requests -> course_change_requests (+ subject_id -> course_id)
--
-- Run against syllabihub_db. A full mysqldump backup was taken
-- immediately before this ran (see Claude's scratchpad db-backups/
-- for this session — not checked into the repo).
-- ============================================================

-- --- subjects -> courses -------------------------------------
RENAME TABLE subjects TO courses;

ALTER TABLE courses DROP INDEX uq_subject_code_active;
ALTER TABLE courses DROP COLUMN subject_code_active;
ALTER TABLE courses DROP INDEX ft_subject_search;
ALTER TABLE courses CHANGE COLUMN subject_code course_code VARCHAR(20) NOT NULL;
ALTER TABLE courses ADD COLUMN course_code_active VARCHAR(20)
    GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN course_code END) VIRTUAL;
ALTER TABLE courses ADD UNIQUE KEY uq_course_code_active (course_code_active);
ALTER TABLE courses ADD FULLTEXT KEY ft_course_search (course_code, title);
ALTER TABLE courses RENAME INDEX idx_subjects_program_id TO idx_courses_program_id;

ALTER TABLE courses DROP FOREIGN KEY fk_subjects_created_by;
ALTER TABLE courses RENAME INDEX fk_subjects_created_by TO fk_courses_created_by;
ALTER TABLE courses ADD CONSTRAINT fk_courses_created_by FOREIGN KEY (created_by) REFERENCES users(id);

-- NOTE: no DROP/ADD needed here — MySQL 8 InnoDB auto-renames an
-- auto-generated `<oldtable>_ibfk_N` constraint to `<newtable>_ibfk_N`
-- the moment RENAME TABLE runs (confirmed by testing this script:
-- `subjects_ibfk_1` was already `courses_ibfk_1` by this point, so the
-- DROP+ADD pair that would have gone here just errors as redundant).
-- Same applies below for syllabi_ibfk_1/subject_change_requests_ibfk_*
-- as FK *targets* (their own constraint names on THEIR OWN table don't
-- auto-rename just because a table they point AT got renamed — only
-- the table each of those already-existing constraints resolves to
-- silently follows the rename, confirmed via SHOW CREATE TABLE syllabi
-- already showing `REFERENCES courses (id)` before syllabi was touched
-- at all).

-- --- syllabi.subject_id -> course_id ---------------------------
ALTER TABLE syllabi DROP FOREIGN KEY syllabi_ibfk_1;
ALTER TABLE syllabi CHANGE COLUMN subject_id course_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE syllabi RENAME INDEX subject_id TO course_id;
ALTER TABLE syllabi ADD CONSTRAINT syllabi_ibfk_1 FOREIGN KEY (course_id) REFERENCES courses(id);

-- --- subject_change_requests -> course_change_requests ---------
-- RENAME TABLE alone already flips subject_change_requests_ibfk_1/2/3
-- to course_change_requests_ibfk_1/2/3 (same auto-rename behavior as
-- above) and ibfk_1 already resolves to `courses` by this point too —
-- only ibfk_1 needs dropping/recreating, purely because its column
-- (subject_id) is being renamed to course_id; ibfk_2/ibfk_3 (->users)
-- are untouched.
RENAME TABLE subject_change_requests TO course_change_requests;
ALTER TABLE course_change_requests DROP FOREIGN KEY course_change_requests_ibfk_1;
ALTER TABLE course_change_requests CHANGE COLUMN subject_id course_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE course_change_requests RENAME INDEX subject_id TO course_id;
ALTER TABLE course_change_requests ADD CONSTRAINT course_change_requests_ibfk_1 FOREIGN KEY (course_id) REFERENCES courses(id);
