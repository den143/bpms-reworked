<?php
require_once __DIR__ . '/../config/database.php';

class ScoreCalculator {

    private static function db() { global $conn; return $conn; }

    public static function calculate($round_id) {
        $db = self::db();
        $round_id = (int)$round_id;

        // 1. Fetch Round Context (To determine if Gatekeeper is needed)
        $r_data = $db->query("SELECT ordering, event_id FROM rounds WHERE id = $round_id")->fetch_assoc();
        $is_prelim = ($r_data['ordering'] == 1);

        // 2. GATEKEEPER LOGIC (The Filter)
        // Round 1 (Prelims): Allow Everyone (Active, Qualified, or Eliminated - history preserved)
        // Round 2+ (Finals): Allow ONLY 'Qualified' ... OR ... those who actually competed (have scores).
        // (The 'OR' clause ensures that when you Lock a round and losers become 'Eliminated', 
        // they don't vanish from the Result Sheet immediately).
        
        $status_clause = "";
        if (!$is_prelim) {
            $status_clause = "AND (
                cd.status = 'Qualified' 
                OR EXISTS (SELECT 1 FROM scores s WHERE s.contestant_id = u.id AND s.round_id = $round_id)
            )";
        }

        // 3. Fetch Active Judges
        $judges = $db->query("SELECT u.id, u.name 
                              FROM users u
                              JOIN event_judges ej ON u.id = ej.judge_id 
                              WHERE ej.event_id = {$r_data['event_id']} 
                              AND ej.status = 'Active' 
                              AND ej.is_deleted = 0")->fetch_all(MYSQLI_ASSOC);
        
        $judge_ids = array_column($judges, 'id');
        $judge_count = count($judge_ids);

        // 4. Fetch Contestants (Applying the Gatekeeper)
        $c_sql = "SELECT u.id as user_id, cd.id as detail_id, u.name, cd.contestant_number, cd.photo 
                  FROM users u
                  JOIN contestant_details cd ON u.id = cd.user_id
                  WHERE cd.event_id = {$r_data['event_id']} 
                  AND u.status = 'Active' 
                  AND cd.is_deleted = 0
                  $status_clause
                  ORDER BY cd.contestant_number ASC";
                  
        $contestants = $db->query($c_sql)->fetch_all(MYSQLI_ASSOC);

        // 5. Fetch Segments & Criteria (OPTIMIZATION: Fetch ALL criteria once)
        $segments = $db->query("SELECT id, weight_percentage FROM segments WHERE round_id = $round_id ORDER BY ordering")->fetch_all(MYSQLI_ASSOC);
        
        $all_criteria = $db->query("SELECT id, segment_id FROM criteria 
                                    WHERE segment_id IN (SELECT id FROM segments WHERE round_id = $round_id)")->fetch_all(MYSQLI_ASSOC);

        // Map criteria by Segment ID to avoid queries in the loop
        $criteria_by_segment = [];
        foreach ($all_criteria as $c) {
            $criteria_by_segment[$c['segment_id']][] = $c['id'];
        }

        // 6. Fetch Scores Map
        $scores_raw = $db->query("SELECT * FROM scores WHERE round_id = $round_id")->fetch_all(MYSQLI_ASSOC);
        $score_map = [];
        foreach ($scores_raw as $s) {
            $score_map[$s['contestant_id']][$s['judge_id']][$s['criteria_id']] = (float)$s['score_value']; 
        }

        // 7. Compute Totals
        $ranking = [];

        foreach ($contestants as $c) {
            $uid = $c['user_id'];
            $grand_total = 0;
            $judge_totals = [];

            foreach ($judge_ids as $jid) {
                $judge_round_total = 0;
                
                foreach ($segments as $seg) {
                    $sid = $seg['id'];
                    $weight = (float)$seg['weight_percentage'] / 100;
                    
                    $criteria_ids = $criteria_by_segment[$sid] ?? [];
                    
                    $seg_score_sum = 0;
                    foreach ($criteria_ids as $crit_id) {
                        $val = $score_map[$uid][$jid][$crit_id] ?? 0;
                        $seg_score_sum += $val;
                    }
                    
                    $judge_round_total += ($seg_score_sum * $weight);
                }

                $judge_totals[$jid] = $judge_round_total; 
                $grand_total += $judge_round_total;
            }

            $final_score = ($judge_count > 0) ? $grand_total / $judge_count : 0;

            // Prepare Display Data
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

        // 8. Sort using RAW float score for accuracy
        usort($ranking, function($a, $b) { 
            return $b['raw_score'] <=> $a['raw_score']; 
        });

        // 9. Rank
        $rank = 1;
        foreach ($ranking as $key => $item) {
            if ($key > 0 && $item['final_score'] == $ranking[$key-1]['final_score']) {
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
        
        $segments = $db->query("SELECT id, title, weight_percentage FROM segments WHERE round_id = $round_id ORDER BY ordering")->fetch_all(MYSQLI_ASSOC);
        $criteria = $db->query("SELECT c.id, c.title, c.max_score, c.segment_id FROM criteria c JOIN segments s ON c.segment_id = s.id WHERE s.round_id = $round_id ORDER BY c.id")->fetch_all(MYSQLI_ASSOC);
        $judges = $db->query("SELECT u.id, u.name FROM users u JOIN event_judges ej ON u.id = ej.judge_id WHERE ej.event_id = (SELECT event_id FROM rounds WHERE id=$round_id) AND ej.status='Active' AND ej.is_deleted=0")->fetch_all(MYSQLI_ASSOC);
        $scores = $db->query("SELECT judge_id, contestant_id, criteria_id, score_value FROM scores WHERE round_id = $round_id")->fetch_all(MYSQLI_ASSOC);
        
        $mapped_scores = [];
        foreach($scores as $s) {
            $mapped_scores[$s['contestant_id']][$s['judge_id']][$s['criteria_id']] = $s['score_value'];
        }

        return ['segments' => $segments, 'criteria' => $criteria, 'judges' => $judges, 'scores' => $mapped_scores];
    }
}
?>