-- Schema change: subject ownership + faculty edit/delete approval workflow
-- Applied: 2026-08-07, approved by Rico
-- Ran directly against syllabihub_db (raw SQL, no Laravel migrations —
-- see CLAUDE.md). Recorded here for the database team (Mary, Vincent) and
-- for anyone re-provisioning the DB from scratch.

-- 1. Track who created each subject. NULL = seeded/legacy subject with no
--    owner — faculty can never request edits/deletes on those, only
--    admin/intern can touch them.
ALTER TABLE subjects
  ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER program_id,
  ADD CONSTRAINT fk_subjects_created_by FOREIGN KEY (created_by) REFERENCES users(id);

-- 2. Faculty-submitted edit/delete requests on subjects they created.
--    Subject data stays unchanged until an admin/intern approves the
--    request (hold-until-approved model).
CREATE TABLE subject_change_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id BIGINT UNSIGNED NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  action ENUM('update','delete') NOT NULL,
  payload JSON NULL COMMENT 'Proposed field changes for update actions; null for delete',
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  review_note VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (subject_id) REFERENCES subjects(id),
  FOREIGN KEY (requested_by) REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- 3. The old "admin assigns subjects to faculty" feature is removed —
--    faculty now create/own their own subjects directly instead. This
--    pivot table is no longer used anywhere in the app.
DROP TABLE faculty_subjects;
