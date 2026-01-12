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
             WHERE manager_id = ? AND status = 'Active' AND is_deleted = 0
             LIMIT 1"
        );

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();
        return $result ?: null;
    }

    public static function create(
        int $userId,
        string $title, 
        string $date,
        string $venue
    ): bool {
        $db = self::db();

        try {
            $db->begin_transaction();

            // 1. Deactivate previous active events for this manager
            $stmt1 = $db->prepare(
                "UPDATE events 
                 SET status = 'Inactive' 
                 WHERE manager_id = ?"
            );
            $stmt1->bind_param("i", $userId);
            $stmt1->execute();

            // 2. Create new Active event
            $stmt2 = $db->prepare(
                "INSERT INTO events 
                 (manager_id, title, event_date, venue, status)
                 VALUES (?, ?, ?, ?, 'Active')"
            );
            $stmt2->bind_param("isss", $userId, $title, $date, $venue);
            $stmt2->execute();

            $db->commit();
            return true;

        } catch (Throwable $e) {
            $db->rollback();
            error_log("Event Create Error: " . $e->getMessage());
            return false;
        }
    }
}