<?php
require_once __DIR__ . '/../config/database.php';

class Event
{
    private static function db() {
        global $conn;
        return $conn;
    }

    public static function getActiveByUser(int $userId): ?array
    {
        $db = self::db();

        $stmt = $db->prepare(
            "SELECT * FROM events 
             WHERE user_id = ? AND status = 'Active' 
             LIMIT 1"
        );

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    public static function create(
        int $userId,
        string $name,
        string $date,
        string $venue
    ): bool {
        $db = self::db();

        try {
            $db->begin_transaction();

            $stmt1 = $db->prepare(
                "UPDATE events 
                 SET status = 'Inactive' 
                 WHERE user_id = ?"
            );
            $stmt1->bind_param("i", $userId);
            $stmt1->execute();

            $stmt2 = $db->prepare(
                "INSERT INTO events 
                 (user_id, name, event_date, venue, status)
                 VALUES (?, ?, ?, ?, 'Active')"
            );
            $stmt2->bind_param("isss", $userId, $name, $date, $venue);
            $stmt2->execute();

            $db->commit();
            return true;

        } catch (Throwable $e) {
            $db->rollback();
            return false;
        }
    }
}
