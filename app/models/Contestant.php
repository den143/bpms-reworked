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
        $params = [$managerId];
        $types = "i";

        if ($status === 'Active') {
            $statusClause = "AND ec.status IN ('Active', 'Qualified', 'Eliminated')";
        } else {
            $statusClause = "AND ec.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        // [FIXED] Added ec.contestant_number to SELECT
        $sql = "SELECT u.id, u.name, u.email, u.status as user_status, 
                       ec.status AS competition_status, 
                       ec.id as contestant_id,
                       ec.contestant_number, 
                       ec.age, ec.height, 
                       ec.bust, ec.waist, ec.hips,
                       CONCAT(ec.bust, '-', ec.waist, '-', ec.hips) as vital_stats, 
                       ec.hometown, ec.motto, ec.photo, ec.event_id,
                       e.title as event_name 
                FROM event_contestants ec 
                JOIN users u ON ec.user_id = u.id 
                JOIN events e ON ec.event_id = e.id 
                WHERE e.manager_id = ? 
                  AND e.status = 'Active' 
                  $statusClause
                  AND ec.is_deleted = 0"; 

        return self::fetchData($sql, $params, $types, $search);
    }

    public static function getAllByOrganizer(int $organizerId, string $status, string $search = ''): array
    {
        $params = [$organizerId];
        $types = "i";

        if ($status === 'Active') {
            $statusClause = "AND ec.status IN ('Active', 'Qualified', 'Eliminated')";
        } else {
            $statusClause = "AND ec.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        // [FIXED] Added ec.contestant_number to SELECT
        $sql = "SELECT u.id, u.name, u.email, u.status as user_status, 
                       ec.status AS competition_status,
                       ec.id as contestant_id,
                       ec.contestant_number,
                       ec.age, ec.height, 
                       ec.bust, ec.waist, ec.hips,
                       CONCAT(ec.bust, '-', ec.waist, '-', ec.hips) as vital_stats,
                       ec.hometown, ec.motto, ec.photo, ec.event_id,
                       e.title as event_name 
                FROM event_contestants ec 
                JOIN users u ON ec.user_id = u.id 
                JOIN events e ON ec.event_id = e.id 
                JOIN event_teams et ON e.id = et.event_id
                WHERE et.user_id = ? 
                  AND et.status = 'Active'
                  AND et.is_deleted = 0
                  AND e.status = 'Active' 
                  $statusClause
                  AND ec.is_deleted = 0";

        return self::fetchData($sql, $params, $types, $search);
    }

    private static function fetchData($sql, $baseParams, $baseTypes, $search) {
        $db = self::db();
        
        if (!empty($search)) {
            $sql .= " AND (u.name LIKE ? OR ec.hometown LIKE ?)";
            $baseTypes .= "ss";
            $searchTerm = "%$search%";
            $baseParams[] = $searchTerm;
            $baseParams[] = $searchTerm;
        }
        
        $sql .= " ORDER BY ec.contestant_number ASC, ec.registered_at DESC"; // Sort by Number first

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            die("SQL Error: " . $db->error);
        }
        
        $stmt->bind_param($baseTypes, ...$baseParams);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>