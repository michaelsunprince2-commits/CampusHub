-- Shared-host/phpMyAdmin version.
-- Run only the statements that match your current database structure.

-- 1. If reviews.status does NOT exist, run these:
ALTER TABLE reviews
  ADD COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER helpful_count;

ALTER TABLE reviews
  ADD INDEX idx_status (status);

UPDATE reviews
  SET status = 'approved'
  WHERE status = 'pending';

-- 2. If platform_reviews.status already exists, run this:
ALTER TABLE platform_reviews
  MODIFY status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending';

-- 3. If platform_reviews.status does NOT exist, run these instead of statement 2:
-- ALTER TABLE platform_reviews
--   ADD COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER helpful_count;
--
-- ALTER TABLE platform_reviews
--   ADD INDEX idx_status (status);
--
-- UPDATE platform_reviews
--   SET status = 'approved'
--   WHERE status = 'pending';
