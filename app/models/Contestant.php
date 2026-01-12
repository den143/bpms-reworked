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
        
        $sql = "SELECT u.id, u.name, u.email, u.status, 
                       ec.status AS competition_status, 
                       ec.age, ec.height, 
                       CONCAT(ec.bust, '-', ec.waist, '-', ec.hips) as vital_stats, 
                       ec.hometown, ec.motto, ec.photo, ec.event_id,
                       e.title as event_name 
                FROM users u 
                JOIN event_contestants ec ON u.id = ec.user_id 
                JOIN events e ON ec.event_id = e.id 
                WHERE e.manager_id = ? 
                  AND e.status = 'Active' 
                  AND u.role = 'Contestant' 
                  AND u.status = ?";
        return self::fetchData($sql, [$managerId, $status], "is", $search);
    }

    public static function getAllByOrganizer(int $organizerId, string $status, string $search = ''): array
    {
        
        $sql = "SELECT u.id, u.name, u.email, u.status, 
                       ec.status AS competition_status,
                       ec.age, ec.height, 
                       CONCAT(ec.bust, '-', ec.waist, '-', ec.hips) as vital_stats,
                       ec.hometown, ec.motto, ec.photo, ec.event_id,
                       e.title as event_name 
                FROM users u 
                JOIN event_contestants ec ON u.id = ec.user_id 
                JOIN events e ON ec.event_id = e.id 
                JOIN event_teams et ON e.id = et.event_id
                WHERE et.user_id = ? 
                  AND et.status = 'Active'
                  AND et.is_deleted = 0
                  AND e.status = 'Active' 
                  AND u.role = 'Contestant' 
                  AND u.status = ?";
        return self::fetchData($sql, [$organizerId, $status], "is", $search);
    }

    private static function fetchData($sql, $baseParams, $baseTypes, $search) {
        $db = self::db();
        
        $sql .= " AND ec.is_deleted = 0 "; 

        if (!empty($search)) {
            $sql .= " AND (u.name LIKE ? OR ec.hometown LIKE ?)";
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