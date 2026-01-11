<?php
// bpms/api/tally.php (READ-ONLY VERSION)
ini_set('display_errors', 0); 
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../app/core/guard.php';
requireLogin(); 
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/ScoreCalculator.php';

// --- HELPER ---
function getRoundMetadata($conn, $round_id) {
    $r_query = $conn->query("SELECT event_id, status, contestants_to_advance FROM rounds WHERE id = $round_id");
    if ($r_query->num_rows === 0) return null;
    $round_data = $r_query->fetch_assoc();
    
    $j_sql = "SELECT u.id, u.name FROM event_judges ej JOIN users u ON ej.judge_id = u.id 
              WHERE ej.event_id = {$round_data['event_id']} AND ej.status = 'Active' ORDER BY u.id ASC";
    $judges = $conn->query($j_sql)->fetch_all(MYSQLI_ASSOC);

    return [
        'round_status' => $round_data['status'],
        'qualifiers' => (int)$round_data['contestants_to_advance'],
        'judges' => $judges
    ];
}

// GET REQUEST ONLY
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $round_id = isset($_GET['round_id']) ? (int)$_GET['round_id'] : 0;
    if ($round_id === 0) { echo json_encode(['error' => 'Round ID required']); exit; }

    $meta = getRoundMetadata($conn, $round_id);
    if (!$meta) { echo json_encode(['error' => 'Invalid Round']); exit; }

    // 1. Calculate Live Scores
    $results = ScoreCalculator::calculate($round_id);

    // 2. If Completed, OVERWRITE with official locked results
    if ($meta['round_status'] === 'Completed') {
        $sql_saved = "SELECT contestant_id, total_score, `rank` FROM round_rankings WHERE round_id = $round_id";
        $saved_q = $conn->query($sql_saved);
        
        $saved_map = [];
        while($row = $saved_q->fetch_assoc()) { $saved_map[$row['contestant_id']] = $row; }

        foreach ($results as $key => $row) {
            $cid = $row['contestant']['detail_id'] ?? $row['contestant']['id'] ?? 0;
            if (isset($saved_map[$cid])) {
                $results[$key]['final_score'] = number_format($saved_map[$cid]['total_score'], 2);
                $results[$key]['rank'] = (int)$saved_map[$cid]['rank'];
            }
        }
        
        usort($results, function($a, $b) { 
            return ($a['rank'] > 0 ? $a['rank'] : 999) <=> ($b['rank'] > 0 ? $b['rank'] : 999); 
        });
    }

    $submitted_ids = [];
    $submitted_q = $conn->query("SELECT judge_id FROM judge_round_status WHERE round_id = $round_id AND status = 'Submitted'");
    while($r = $submitted_q->fetch_assoc()) { $submitted_ids[] = (int)$r['judge_id']; }

    echo json_encode([
        'status' => 'success',
        'round_status' => $meta['round_status'],
        'qualifiers' => $meta['qualifiers'],
        'judges' => $meta['judges'],
        'submitted_judges' => $submitted_ids,
        'ranking' => $results,
        'audit' => ScoreCalculator::getAuditData($round_id)
    ]);
    exit();
}
?>