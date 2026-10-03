-- Only needed if you imported your own exported database instead of full_database.sql
CREATE TABLE IF NOT EXISTS php_sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    data MEDIUMBLOB NOT NULL,
    last_activity INT NOT NULL,
    INDEX idx_last_activity (last_activity)
);
