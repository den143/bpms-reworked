<?php
require_once __DIR__ . '/../config/database.php';

class AwardCalculator {

    public static function getAwardsList($event_id) {
        global $conn;
        
        // Matches 'awards' table
        $sql = "SELECT * FROM awards WHERE event_id = ? AND is_deleted = 0 ORDER BY id ASC";        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $awards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $results = [];

        foreach ($awards as $award) {
            $winner = null;
            $candidates = []; 

            switch ($award['selection_method']) {
                case 'Manual':
                    $winner = self::getStoredWinner($award['id']);
                    break;

                case 'Audience_Vote':
                    $winner = self::getAudienceWinner($event_id);
                    $candidates = self::getAudienceTally($event_id);
                    break;

                case 'Highest_Segment':
                    if ($award['linked_segment_id']) {
                        $winner = self::getSegmentWinner($award['linked_segment_id']);
                    }
                    break;

                case 'Highest_Round':
                    if ($award['linked_round_id']) {
                        $winner = self::getRoundWinner($award['linked_round_id']);
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
        // Manual winners are stored in 'award_winners' linked to 'event_contestants'
        $sql = "SELECT ec.id, u.name, ec.photo, aw.title_label 
                FROM award_winners aw 
                JOIN event_contestants ec ON aw.contestant_id = ec.id
                JOIN users u ON ec.user_id = u.id 
                WHERE aw.award_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $award_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private static function getAudienceWinner($event_id) {
        global $conn;
        // FIX: Added u.name and ec.photo to GROUP BY
        $sql = "SELECT ec.id, u.name, ec.photo, COUNT(av.id) as votes 
                FROM audience_votes av 
                JOIN event_contestants ec ON av.contestant_id = ec.id 
                JOIN users u ON ec.user_id = u.id 
                WHERE ec.event_id = ? 
                GROUP BY ec.id, u.name, ec.photo
                ORDER BY votes DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private static function getAudienceTally($event_id) {
        global $conn;
        // FIX: Added u.name to GROUP BY
        $sql = "SELECT ec.id, u.name, COUNT(av.id) as votes 
                FROM audience_votes av 
                JOIN event_contestants ec ON av.contestant_id = ec.id 
                JOIN users u ON ec.user_id = u.id 
                WHERE ec.event_id = ? 
                GROUP BY ec.id, u.name 
                ORDER BY votes DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private static function getSegmentWinner($segment_id) {
        global $conn;
        // FIX: Added u.name and ec.photo to GROUP BY
        // 1. 'scores' table now links directly to 'event_contestants' via 'contestant_id'
        // 2. Joining 'criteria' to filter by 'segment_id'
        $sql = "SELECT ec.id, u.name, ec.photo, SUM(s.score_value) as total_score 
                FROM scores s 
                JOIN criteria c ON s.criteria_id = c.id 
                JOIN event_contestants ec ON s.contestant_id = ec.id 
                JOIN users u ON ec.user_id = u.id 
                WHERE c.segment_id = ? 
                GROUP BY ec.id, u.name, ec.photo
                ORDER BY total_score DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $segment_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private static function getRoundWinner($round_id) {
        global $conn;
        // Uses 'event_contestants' (ec)
        // Check table exists first (Prevent crash if no rounds locked yet)
        $check = $conn->query("SHOW TABLES LIKE 'round_rankings'");
        if(!$check || $check->num_rows == 0) return null;

        $sql = "SELECT ec.id, u.name, ec.photo, rr.total_score 
                FROM round_rankings rr 
                JOIN event_contestants ec ON rr.contestant_id = ec.id 
                JOIN users u ON ec.user_id = u.id 
                WHERE rr.round_id = ? AND rr.rank = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $round_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
?>