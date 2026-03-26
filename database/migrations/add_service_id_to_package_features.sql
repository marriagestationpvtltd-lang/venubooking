-- Migration: Add service_id to service_package_features
-- Links each package feature to its corresponding additional_services row so
-- that the service photo can be retrieved and displayed as a rounded icon on
-- the public package-detail page.  The column is nullable to preserve
-- backward compatibility with existing features stored as free text.

DELIMITER $$

DROP PROCEDURE IF EXISTS add_service_id_to_package_features $$
CREATE PROCEDURE add_service_id_to_package_features()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name   = 'service_package_features'
          AND column_name  = 'service_id'
    ) THEN
        ALTER TABLE service_package_features
            ADD COLUMN service_id INT DEFAULT NULL
                COMMENT 'FK → additional_services.id; NULL for legacy free-text features'
            AFTER package_id,
            ADD INDEX idx_spf_service_id (service_id),
            ADD CONSTRAINT fk_spf_service_id
                FOREIGN KEY (service_id) REFERENCES additional_services(id) ON DELETE SET NULL;
    END IF;
END $$

DELIMITER ;

CALL add_service_id_to_package_features();
DROP PROCEDURE IF EXISTS add_service_id_to_package_features;
