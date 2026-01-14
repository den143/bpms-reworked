<?php
// app/models/ScoreCalculator.php
// Purpose: The math engine. Calculates weighted averages and ranks contestants.

require_once __DIR__ . '/../config/database.php';

class ScoreCalculator {

    private static function db() { global $conn; return $conn; }

    public static function calculate($round_id) {
        $db = self::db();
        $round_id = (int)$round_id;

        // 1. Fetch Round Context
        $r_data = $db->query("SELECT ordering, event_id FROM rounds WHERE id = $round_id")->fetch_assoc();
        $is_prelim = ($r_data['ordering'] == 1);
        $event_id = $r_data['event_id'];

        // 2. GATEKEEPER LOGIC (The Filter)
        // Decides who appears on the result sheet.
        // Logic: Show anyone "Qualified" OR anyone who has at least one score for this round.
        // We use 'ec' alias for event_contestants.
        $status_clause = "";
        if (!$is_prelim) {
            $status_clause = "AND (
                ec.status = 'Qualified' 
                OR EXISTS (
                    SELECT 1 FROM scores sc
                    JOIN criteria c ON sc.criteria_id = c.id
                    JOIN segments s ON c.segment_id = s.id
                    WHERE sc.contestant_id = ec.id AND s.round_id = $round_id
                )
            )";
        }

        // 3. Fetch Active Judges
        // Joins 'event_judges' -> 'users'
        $judges = $db->query("SELECT u.id, u.name 
                              FROM users u
                              JOIN event_judges ej ON u.id = ej.judge_id 
                              WHERE ej.event_id = $event_id 
                              AND ej.status = 'Active' 
                              AND ej.is_deleted = 0")->fetch_all(MYSQLI_ASSOC);
        
        $judge_ids = array_column($judges, 'id');
        $judge_count = count($judge_ids);

        // 4. Fetch Contestants (Applying the Gatekeeper)
        $c_sql = "SELECT u.id as user_id, ec.id as detail_id, u.name, ec.contestant_number, ec.photo, ec.hometown 
                  FROM users u
                  JOIN event_contestants ec ON u.id = ec.user_id
                  WHERE ec.event_id = $event_id 
                  AND u.status = 'Active' 
                  AND ec.is_deleted = 0
                  $status_clause
                  ORDER BY ec.contestant_number ASC";
                  
        $contestants = $db->query($c_sql)->fetch_all(MYSQLI_ASSOC);

        // 5. Fetch Segments & Criteria
        $segments = $db->query("SELECT id, weight_percent FROM segments WHERE round_id = $round_id AND is_deleted = 0 ORDER BY ordering")->fetch_all(MYSQLI_ASSOC);
        
        // UPDATED: Added 'is_deleted = 0' to subquery to ensure criteria from deleted segments are ignored in calculations too
        $all_criteria = $db->query("SELECT id, segment_id FROM criteria 
                                    WHERE is_deleted = 0 AND segment_id IN (SELECT id FROM segments WHERE round_id = $round_id AND is_deleted = 0)")->fetch_all(MYSQLI_ASSOC);

        // Map criteria by Segment ID
        $criteria_by_segment = [];
        foreach ($all_criteria as $c) {
            $criteria_by_segment[$c['segment_id']][] = $c['id'];
        }

        // 6. Fetch Scores Map
        $sql_scores = "SELECT sc.contestant_id, sc.judge_id, sc.criteria_id, sc.score_value
                       FROM scores sc
                       JOIN criteria c ON sc.criteria_id = c.id
                       JOIN segments s ON c.segment_id = s.id
                       WHERE s.round_id = $round_id";
                       
        $scores_raw = $db->query($sql_scores)->fetch_all(MYSQLI_ASSOC);
        
        $score_map = [];
        // Map: [ContestantID][JudgeID][CriteriaID] = Score
        foreach ($scores_raw as $s) {
            $score_map[$s['contestant_id']][$s['judge_id']][$s['criteria_id']] = (float)$s['score_value']; 
        }

        // 7. Compute Totals
        $ranking = [];

        foreach ($contestants as $c) {
            // Note: $c['detail_id'] matches 'event_contestants.id' which is used in scores table
            $cid = $c['detail_id']; 
            $grand_total = 0;
            $judge_totals = [];

            foreach ($judge_ids as $jid) {
                $judge_round_total = 0;
                
                foreach ($segments as $seg) {
                    $sid = $seg['id'];
                    // Matches 'weight_percent' column
                    $weight = (float)$seg['weight_percent'] / 100;
                    
                    $criteria_ids = $criteria_by_segment[$sid] ?? [];
                    
                    $seg_score_sum = 0;
                    foreach ($criteria_ids as $crit_id) {
                        $val = $score_map[$cid][$jid][$crit_id] ?? 0;
                        $seg_score_sum += $val;
                    }
                    
                    $judge_round_total += ($seg_score_sum * $weight);
                }

                $judge_totals[$jid] = $judge_round_total; 
                $grand_total += $judge_round_total;
            }

            $final_score = ($judge_count > 0) ? $grand_total / $judge_count : 0;

            // Format for display
            $formatted_judge_scores = [];
            foreach($judge_totals as $j_id => $sc) {
                $formatted_judge_scores[$j_id] = number_format($sc, 2);
            }

            $ranking[] = [
                'contestant' => $c,
                'judge_scores' => $formatted_judge_scores, 
                'raw_score' => $final_score,               
                'final_score' => number_format($final_score, 2), 
                'rank' => 0
            ];
        }

        // 8. Sort (High to Low)
        usort($ranking, function($a, $b) { 
            return $b['raw_score'] <=> $a['raw_score']; 
        });

        // 9. Assign Ranks (Handle Ties)
        $rank = 1;
        foreach ($ranking as $key => $item) {
            // If score is same as previous, give same rank (1, 2, 2, 4...)
            if ($key > 0 && abs($item['raw_score'] - $ranking[$key-1]['raw_score']) < 0.001) {
                $ranking[$key]['rank'] = $ranking[$key-1]['rank'];
            } else {
                $ranking[$key]['rank'] = $rank;
            }
            $rank++;
        }

        return $ranking;
    }

    public static function getAuditData($round_id) { 
        $db = self::db();
        $round_id = (int)$round_id;
        
        // Debugging / Audit Tool Logic
        
        // FIX 1: Filter out deleted segments
        $segments = $db->query("SELECT id, title, weight_percent FROM segments WHERE round_id = $round_id AND is_deleted = 0 ORDER BY ordering")->fetch_all(MYSQLI_ASSOC);
        
        // FIX 2: Filter out deleted criteria (and criteria linked to deleted segments)
        $criteria = $db->query("SELECT c.id, c.title, c.max_score, c.segment_id 
                                FROM criteria c 
                                JOIN segments s ON c.segment_id = s.id 
                                WHERE s.round_id = $round_id 
                                AND s.is_deleted = 0 
                                AND c.is_deleted = 0 
                                ORDER BY c.id")->fetch_all(MYSQLI_ASSOC);
        
        $judges = $db->query("SELECT u.id, u.name FROM users u JOIN event_judges ej ON u.id = ej.judge_id WHERE ej.event_id = (SELECT event_id FROM rounds WHERE id=$round_id) AND ej.status='Active' AND ej.is_deleted=0")->fetch_all(MYSQLI_ASSOC);
        
        $sql_scores = "SELECT sc.judge_id, sc.contestant_id, sc.criteria_id, sc.score_value 
                       FROM scores sc
                       JOIN criteria c ON sc.criteria_id = c.id
                       JOIN segments s ON c.segment_id = s.id
                       WHERE s.round_id = $round_id";
        $scores = $db->query($sql_scores)->fetch_all(MYSQLI_ASSOC);
        
        $mapped_scores = [];
        foreach($scores as $s) {
            $mapped_scores[$s['contestant_id']][$s['judge_id']][$s['criteria_id']] = $s['score_value'];
        }

        return ['segments' => $segments, 'criteria' => $criteria, 'judges' => $judges, 'scores' => $mapped_scores];
    }
}
?>