-- Schema change: track each syllabus file's original upload filename
-- Applied: 2026-08-12, approved by Rico
-- Ran directly against syllabihub_db (raw SQL, no Laravel migrations —
-- see CLAUDE.md). Recorded here retroactively — this ALTER was already
-- run against the live dev DB before being committed here. Recorded for
-- the database team (Mary, Vincent) and for anyone re-provisioning the
-- DB from scratch.

-- Users upload files named e.g. "COMP016-syllabus-final-v3.pdf", but
-- file_path is a generated storage path (syllabi/{subject_id}/{hash}.pdf)
-- with no trace of that name. Store the original client filename
-- alongside it purely for display (subjects.show / subjects.edit list
-- each subject's syllabi by their original name instead of the storage
-- path's basename).
ALTER TABLE syllabi
  ADD COLUMN original_filename VARCHAR(255) NULL AFTER file_type;
