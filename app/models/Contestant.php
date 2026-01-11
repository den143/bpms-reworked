<?php
require_once __DIR__ . '/../config/database.php';

class Contestant
{
    private static function db() {
        global $conn;
        return $conn;
    }

    public static function getAllByManager(int $managerId, string $status, string $search = ''): array
    {
        $db = self::db();
        // UPDATED: Added cd.status AS competition_status
        $sql = "SELECT u.id, u.name, u.email, u.status, 
                       cd.status AS competition_status, 
                       cd.age, cd.height, cd.vital_stats, cd.hometown, cd.motto, cd.photo, cd.event_id,
                       e.name as event_name 
                FROM users u 
                JOIN contestant_details cd ON u.id = cd.user_id 
                JOIN events e ON cd.event_id = e.id 
                WHERE e.user_id = ? 
                  AND e.status = 'Active' 
                  AND u.role = 'Contestant' 
                  AND u.status = ?";
        return self::fetchData($sql, [$managerId, $status], "is", $search);
    }

    public static function getAllByOrganizer(int $organizerId, string $status, string $search = ''): array
    {
        // UPDATED: Added cd.status AS competition_status
        $sql = "SELECT u.id, u.name, u.email, u.status, 
                       cd.status AS competition_status,
                       cd.age, cd.height, cd.vital_stats, cd.hometown, cd.motto, cd.photo, cd.event_id,
                       e.name as event_name 
                FROM users u 
                JOIN contestant_details cd ON u.id = cd.user_id 
                JOIN events e ON cd.event_id = e.id 
                JOIN event_organizers eo ON e.id = eo.event_id
                WHERE eo.user_id = ? 
                  AND eo.status = 'Active'
                  AND e.status = 'Active' 
                  AND u.role = 'Contestant' 
                  AND u.status = ?";
        return self::fetchData($sql, [$organizerId, $status], "is", $search);
    }

    private static function fetchData($sql, $baseParams, $baseTypes, $search) {
        $db = self::db();
        $sql .= " AND cd.is_deleted = 0 "; 

        if (!empty($search)) {
            $sql .= " AND (u.name LIKE ? OR cd.hometown LIKE ?)";
            $baseTypes .= "ss";
            $searchTerm = "%$search%";
            $baseParams[] = $searchTerm;
            $baseParams[] = $searchTerm;
        }
        $sql .= " ORDER BY u.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param($baseTypes, ...$baseParams);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}