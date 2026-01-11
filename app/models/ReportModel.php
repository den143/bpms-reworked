<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ScoreCalculator.php';
require_once __DIR__ . '/AwardCalculator.php';

class ReportModel {

    public static function generate($event_id) {
        global $conn;
        
        $report = [];

        // 1. EVENT DETAILS
        $sql_evt = "SELECT * FROM events WHERE id = ?";
        $stmt = $conn->prepare($sql_evt);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $report['event'] = $stmt->get_result()->fetch_assoc();

        if (!$report['event']) return null;

        // 2. OFFICIALS (Judges & Organizers)
        // Fetches u.id to map scores in the table later
        $sql_judges = "SELECT u.id, u.name, ej.is_chairman 
                       FROM event_judges ej 
                       JOIN users u ON ej.judge_id = u.id 
                       WHERE ej.event_id = ? AND ej.status = 'Active' 
                       ORDER BY ej.is_chairman DESC, u.name ASC";
        $stmt_j = $conn->prepare($sql_judges);
        $stmt_j->bind_param("i", $event_id);
        $stmt_j->execute();
        $report['judges'] = $stmt_j->get_result()->fetch_all(MYSQLI_ASSOC);

        // Fetch Organizers
        $sql_org = "SELECT u.name, u.role 
                    FROM event_organizers eo 
                    JOIN users u ON eo.user_id = u.id 
                    WHERE eo.event_id = ? AND eo.status = 'Active'";
        $stmt_o = $conn->prepare($sql_org);
        $stmt_o->bind_param("i", $event_id);
        $stmt_o->execute();
        $report['organizers'] = $stmt_o->get_result()->fetch_all(MYSQLI_ASSOC);

        // 3. CONTESTANT MASTER LIST (FIXED: Only Active Contestants)
        $sql_cont = "SELECT u.name, cd.contestant_number, cd.age, cd.hometown, cd.vital_stats 
                     FROM contestant_details cd 
                     JOIN users u ON cd.user_id = u.id 
                     WHERE cd.event_id = ? AND u.status = 'Active' 
                     ORDER BY cd.contestant_number ASC";
        $stmt_c = $conn->prepare($sql_cont);
        $stmt_c->bind_param("i", $event_id);
        $stmt_c->execute();
        $report['contestants'] = $stmt_c->get_result()->fetch_all(MYSQLI_ASSOC);

        // 4. AWARDS SUMMARY
        $awards_raw = AwardCalculator::getAwardsList($event_id);
        $report['awards'] = [];
        foreach($awards_raw as $a) {
            $report['awards'][] = [
                'title' => $a['award']['title'],
                'type' => $a['award']['type'],
                'winner' => $a['winner'] ? $a['winner']['name'] : 'Not Awarded'
            ];
        }

        // 5. ROUNDS DATA
        $sql_r = "SELECT id, title, status FROM rounds WHERE event_id = ? ORDER BY ordering";
        $stmt_r = $conn->prepare($sql_r);
        $stmt_r->bind_param("i", $event_id);
        $stmt_r->execute();
        $rounds = $stmt_r->get_result()->fetch_all(MYSQLI_ASSOC);

        $report['rounds'] = [];

        foreach ($rounds as $round) {
            $r_id = $round['id'];
            
            // A. Get Leaderboard (Ranks)
            $leaderboard = ScoreCalculator::calculate($r_id);
            
            // B. Get Audit Matrix (Detailed Scores)
            $audit = ScoreCalculator::getAuditData($r_id);

            $report['rounds'][] = [
                'info' => $round,
                'leaderboard' => $leaderboard,
                'audit' => $audit
            ];
        }

        return $report;
    }
}
?>