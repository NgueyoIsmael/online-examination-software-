<?php
// includes/db_session.php  (NEW)
// Vercel functions have no permanent disk, so PHP's normal file sessions would log people out
// at random. This saves sessions in the database table php_sessions instead.
require_once __DIR__ . '/db_connect.php';

class DbSessionHandler implements SessionHandlerInterface
{
    private $db = null;

    private function db() {
        if ($this->db === null) {
            $this->db = app_db_connect();
        }
        return $this->db;
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false {
        $stmt = $this->db()->prepare("SELECT data FROM php_sessions WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (string)$row['data'] : '';
    }

    public function write(string $id, string $data): bool {
        $now = time();
        $stmt = $this->db()->prepare("REPLACE INTO php_sessions (id, data, last_activity) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $id, $data, $now);
        return $stmt->execute();
    }

    public function destroy(string $id): bool {
        $stmt = $this->db()->prepare("DELETE FROM php_sessions WHERE id = ?");
        $stmt->bind_param("s", $id);
        return $stmt->execute();
    }

    public function gc(int $max_lifetime): int|false {
        $limit = time() - $max_lifetime;
        $stmt = $this->db()->prepare("DELETE FROM php_sessions WHERE last_activity < ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->affected_rows;
    }
}
