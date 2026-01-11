<?php
require_once __DIR__ . '/../config/database.php';

class AwardCalculator {

    public static function getAwardsList($event_id) {
        global $conn;
        
        $sql = "SELECT * FROM awards WHERE event_id = ? AND is_deleted = 0 ORDER BY type, title";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $awards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $results = [];

        foreach ($awards as $award) {
            $winner = null;
            $candidates = []; 

            switch ($award['source_type']) {
                case 'Manual':
                    $winner = self::getStoredWinner($award['id']);
                    break;

                case 'Audience':
                    // UPDATED: Use the 'tickets' table directly
                    $winner = self::getAudienceWinner($event_id);
                    $candidates = self::getAudienceTally($event_id);
                    break;

                case 'Segment':
                    if ($award['source_id']) {
                        $winner = self::getSegmentWinner($award['source_id']);
                    }
                    break;

                case 'Round':
                    if ($award['source_id']) {
                        $winner = self::getRoundWinner($award['source_id']);
                    }
                    break;
            }

            $results[] = [
                'award' => $award,
                'winner' => $winner,
                'data' => $candidates
            ];
        }

        return $results;
    }

    private static function getStoredWinner($award_id) {
        global $conn;
        // Manual winners are stored in award_winners using contestant_details.id
        $sql = "SELECT cd.id, u.name, cd.photo 
                FROM award_winners aw 
                JOIN contestant_details cd ON aw.contestant_id = cd.id
                JOIN users u ON cd.user_id = u.id 
                WHERE aw.award_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $award_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private static function getAudienceWinner($event_id) {
        global $conn;
        // UPDATED: Query the 'tickets' table
        // Assumes voted_contestant_id refers to contestant_details.id
        $sql = "SELECT cd.id, u.name, cd.photo, COUNT(t.id) as votes 
                FROM tickets t 
                JOIN contestant_details cd ON t.voted_contestant_id = cd.id 
                JOIN users u ON cd.user_id = u.id 
                WHERE t.event_id = ? AND t.status = 'Used' AND t.voted_contestant_id IS NOT NULL
                GROUP BY t.voted_contestant_id 
                ORDER BY votes DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private static function getAudienceTally($event_id) {
        global $conn;
        // UPDATED: Query the 'tickets' table
        $sql = "SELECT cd.id, u.name, COUNT(t.id) as votes 
                FROM tickets t 
                JOIN contestant_details cd ON t.voted_contestant_id = cd.id 
                JOIN users u ON cd.user_id = u.id 
                WHERE t.event_id = ? AND t.status = 'Used' AND t.voted_contestant_id IS NOT NULL
                GROUP BY t.voted_contestant_id 
                ORDER BY votes DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private static function getSegmentWinner($segment_id) {
        global $conn;
        // Segment scores are in 'scores' table using user_id
        // We calculate total, then map back to contestant_details for display
        $sql = "SELECT cd.id, u.name, cd.photo, SUM(s.score_value) as total_score 
                FROM scores s 
                JOIN criteria c ON s.criteria_id = c.id 
                JOIN contestant_details cd ON s.contestant_id = cd.user_id -- Link score(user_id) to detail(user_id)
                JOIN users u ON cd.user_id = u.id 
                WHERE c.segment_id = ? 
                GROUP BY s.contestant_id 
                ORDER BY total_score DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $segment_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private static function getRoundWinner($round_id) {
        global $conn;
        // Round rankings use contestant_id which links to contestant_details.id
        $sql = "SELECT cd.id, u.name, cd.photo, rr.total_score 
                FROM round_rankings rr 
                JOIN contestant_details cd ON rr.contestant_id = cd.id 
                JOIN users u ON cd.user_id = u.id 
                WHERE rr.round_id = ? AND rr.rank = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $round_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
?>