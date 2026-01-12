<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ScoreCalculator.php';
require_once __DIR__ . '/AwardCalculator.php';

class ReportModel {

    public static function generate($event_id) {
        global $conn;
        
        $report = [];

        // 1. EVENT DETAILS
        // Matches 'events' table
        $sql_evt = "SELECT * FROM events WHERE id = ?";
        $stmt = $conn->prepare($sql_evt);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $report['event'] = $stmt->get_result()->fetch_assoc();

        if (!$report['event']) return null;

        // 2. OFFICIALS (Judges & Organizers)
        // A. Judges
        $sql_judges = "SELECT u.id, u.name, ej.is_chairman 
                       FROM event_judges ej 
                       JOIN users u ON ej.judge_id = u.id 
                       WHERE ej.event_id = ? AND ej.status = 'Active' AND ej.is_deleted = 0
                       ORDER BY ej.is_chairman DESC, u.name ASC";
        $stmt_j = $conn->prepare($sql_judges);
        $stmt_j->bind_param("i", $event_id);
        $stmt_j->execute();
        $report['judges'] = $stmt_j->get_result()->fetch_all(MYSQLI_ASSOC);

        // B. Organizers 
        $sql_org = "SELECT u.name, et.role 
                    FROM event_teams et 
                    JOIN users u ON et.user_id = u.id 
                    WHERE et.event_id = ? AND et.status = 'Active' AND et.is_deleted = 0";
        $stmt_o = $conn->prepare($sql_org);
        $stmt_o->bind_param("i", $event_id);
        $stmt_o->execute();
        $report['organizers'] = $stmt_o->get_result()->fetch_all(MYSQLI_ASSOC);

        // 3. CONTESTANT MASTER LIST (UPDATED)
        // Replaced 'contestant_details' with 'event_contestants'
        // Logic: Vital stats are now separate columns, so we CONCAT them for the report.
        $sql_cont = "SELECT u.name, ec.contestant_number, ec.age, ec.hometown, 
                            CONCAT(ec.bust, '-', ec.waist, '-', ec.hips) as vital_stats 
                     FROM event_contestants ec 
                     JOIN users u ON ec.user_id = u.id 
                     WHERE ec.event_id = ? AND u.status = 'Active' AND ec.is_deleted = 0
                     ORDER BY ec.contestant_number ASC";
        $stmt_c = $conn->prepare($sql_cont);
        $stmt_c->bind_param("i", $event_id);
        $stmt_c->execute();
        $report['contestants'] = $stmt_c->get_result()->fetch_all(MYSQLI_ASSOC);

        // 4. AWARDS SUMMARY
        // Uses AwardCalculator logic.
        if (class_exists('AwardCalculator')) {
            $awards_raw = AwardCalculator::getAwardsList($event_id);
            $report['awards'] = [];
            foreach($awards_raw as $a) {
                $report['awards'][] = [
                    'title' => $a['award']['title'],
                    'type' => $a['award']['category_type'] ?? 'Minor', 
                    'winner' => $a['winner'] ? $a['winner']['name'] : 'Not Awarded'
                ];
            }
        } else {
            $report['awards'] = [];
        }

        // 5. ROUNDS DATA
        $sql_r = "SELECT id, title, status FROM rounds WHERE event_id = ? AND is_deleted = 0 ORDER BY ordering";
        $stmt_r = $conn->prepare($sql_r);
        $stmt_r->bind_param("i", $event_id);
        $stmt_r->execute();
        $rounds = $stmt_r->get_result()->fetch_all(MYSQLI_ASSOC);

        $report['rounds'] = [];

        foreach ($rounds as $round) {
            $r_id = $round['id'];
            
            // A. Get Leaderboard (Ranks) from ScoreCalculator
            $leaderboard = ScoreCalculator::calculate($r_id);
            
            // B. Get Audit Matrix (Detailed Scores) from ScoreCalculator
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