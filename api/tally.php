<?php
// bpms/api/tally.php (READ-ONLY VERSION)
// Purpose: Feeds the live "Tabulation Dashboard" with real-time scores.

ini_set('display_errors', 0); 
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../app/core/guard.php';
requireLogin(); 
// Security: Only internal staff can see the tally, not judges or contestants.
requireRole(['Event Manager', 'Tabulator']);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/ScoreCalculator.php';

// --- HELPER FUNCTION ---
function getRoundMetadata($conn, $round_id) {
    $r_query = $conn->query("SELECT event_id, status, qualify_count FROM rounds WHERE id = $round_id");
    
    if ($r_query->num_rows === 0) return null;
    $round_data = $r_query->fetch_assoc();
    
    // Fetch Active Judges for this event
    $j_sql = "SELECT u.id, u.name 
              FROM event_judges ej 
              JOIN users u ON ej.judge_id = u.id 
              WHERE ej.event_id = {$round_data['event_id']} 
              AND ej.status = 'Active' 
              AND ej.is_deleted = 0 
              ORDER BY u.id ASC";
              
    $judges = $conn->query($j_sql)->fetch_all(MYSQLI_ASSOC);

    return [
        'round_status' => $round_data['status'],
        'qualifiers' => (int)$round_data['qualify_count'],
        'judges' => $judges
    ];
}

// GET REQUEST ONLY
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $round_id = isset($_GET['round_id']) ? (int)$_GET['round_id'] : 0;
    
    if ($round_id === 0) { 
        echo json_encode(['error' => 'Round ID required']); 
        exit; 
    }

    $meta = getRoundMetadata($conn, $round_id);
    if (!$meta) { 
        echo json_encode(['error' => 'Invalid Round']); 
        exit; 
    }

    // 1. Calculate Live Scores
    // This calls the complex math logic in your model
    $results = ScoreCalculator::calculate($round_id);

    // 2. Logic: Freezing Results
    // If the round is 'Completed', we DO NOT calculate live anymore. 
    if ($meta['round_status'] === 'Completed') {
        
        // Note: Assuming 'round_rankings' table exists (created by api\rounds.php)
        $sql_saved = "SELECT contestant_id, total_score, `rank` FROM round_rankings WHERE round_id = $round_id";
        $saved_q = $conn->query($sql_saved);
        
        if ($saved_q) {
            $saved_map = [];
            while($row = $saved_q->fetch_assoc()) { 
                $saved_map[$row['contestant_id']] = $row; 
            }

            // Overwrite live calculation with saved snapshot
            foreach ($results as $key => $row) {
                // Handle different array structures safely
                $cid = $row['contestant_id'] ?? $row['contestant']['id'] ?? 0;
                
                if (isset($saved_map[$cid])) {
                    $results[$key]['final_score'] = number_format($saved_map[$cid]['total_score'], 2);
                    $results[$key]['rank'] = (int)$saved_map[$cid]['rank'];
                }
            }
            
            // Re-sort based on frozen rank
            usort($results, function($a, $b) { 
                return ($a['rank'] > 0 ? $a['rank'] : 999) <=> ($b['rank'] > 0 ? $b['rank'] : 999); 
            });
        }
    }

    // 3. Status Check: Who has submitted?
    // Matches 'judge_round_status' table
    $submitted_ids = [];
    $submitted_q = $conn->query("SELECT judge_id FROM judge_round_status WHERE round_id = $round_id AND status = 'Submitted'");
    while($r = $submitted_q->fetch_assoc()) { 
        $submitted_ids[] = (int)$r['judge_id']; 
    }

    // 4. Return JSON Payload
    echo json_encode([
        'status' => 'success',
        'round_status' => $meta['round_status'],
        'qualifiers' => $meta['qualifiers'],
        'judges' => $meta['judges'],
        'submitted_judges' => $submitted_ids,
        'ranking' => $results,
        'audit' => (isset($_GET['audit']) && $_GET['audit'] === 'true') ? ScoreCalculator::getAuditData($round_id) : null
    ]);
    exit();
}
?>