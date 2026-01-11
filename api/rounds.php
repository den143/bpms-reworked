<?php
// bpms/api/rounds.php
// COMBINED: Configuration (CRUD) + Traffic Controller (State Machine)

require_once __DIR__ . '/../app/core/guard.php';
requireLogin(); 
requireRole(['Event Manager', 'Tabulator']); // Allow Tabulator to Lock, but only Manager to Configure
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/ScoreCalculator.php';

// 1. Validate "Funnel Logic" (Configuration)
function validateAdvancement($conn, $event_id, $current_order, $current_top_n) {
    if ($current_top_n < 1) return "Invalid Number: You must advance at least 1 contestant.";

    $prev_order = $current_order - 1;
    if ($prev_order < 1) return true; 

    $stmt = $conn->prepare("SELECT contestants_to_advance, advancement_rule, title FROM rounds WHERE event_id = ? AND ordering = ?");
    $stmt->bind_param("ii", $event_id, $prev_order);
    $stmt->execute();
    $prev = $stmt->get_result()->fetch_assoc();

    if ($prev) {
        $prev_n = (int)$prev['contestants_to_advance'];
        
        if ($prev['advancement_rule'] === 'winner') {
            return "Action Denied: The previous round '{$prev['title']}' already declared a Final Winner.";
        }
        if ($current_top_n >= $prev_n) {
            return "Invalid Configuration: Winners ($current_top_n) must be LESS than previous round ($prev_n).";
        }
    }
    return true;
}

// 2. Strict Configuration Check (100% Total)
function validateRoundConfiguration($conn, $round_id) {
    // A. Check if Segments sum to 100%
    $stmt = $conn->prepare("SELECT SUM(weight_percentage) as total FROM segments WHERE round_id = ?");
    $stmt->bind_param("i", $round_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $seg_total = (float)($res['total'] ?? 0);

    if ($seg_total < 99.9 || $seg_total > 100.1) {
        return "Cannot Start: Total Segment Weight is $seg_total%. It must be exactly 100%.";
    }

    // B. Check if EACH Segment has 100 Points of Criteria
    $sql = "SELECT s.title, SUM(c.max_score) as criteria_total 
            FROM segments s 
            LEFT JOIN criteria c ON s.id = c.segment_id 
            WHERE s.round_id = ? 
            GROUP BY s.id 
            HAVING criteria_total != 100.00 OR criteria_total IS NULL";
    $stmt2 = $conn->prepare($sql);
    $stmt2->bind_param("i", $round_id);
    $stmt2->execute();
    $invalid_segments = $stmt2->get_result();

    if ($invalid_segments->num_rows > 0) {
        $bad_seg = $invalid_segments->fetch_assoc();
        $bad_score = (float)($bad_seg['criteria_total'] ?? 0);
        return "Cannot Start: Segment '{$bad_seg['title']}' has incomplete criteria ($bad_score/100 pts).";
    }

    return true;
}

// 3. The Gatekeeper Check (Qualified Candidates)
function checkGatekeeper($conn, $event_id, $round_ordering) {
    // If this is Round 2, 3, etc... we must have 'Qualified' people waiting.
    if ($round_ordering > 1) {
        $q_check = $conn->query("SELECT COUNT(*) as cnt FROM contestant_details WHERE event_id = $event_id AND status = 'Qualified'")->fetch_assoc();
        if ($q_check['cnt'] == 0) {
            return "EMPTY ROSTER: This is Round $round_ordering, but no contestants are marked as 'Qualified'. Did you forget to lock the previous round?";
        }
    }
    return true;
}

//  PART A: CRUD OPERATIONS (Returns Redirects for Forms)

// --- 1. ADD ROUND ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    requireRole('Event Manager'); // Strict Role Check

    $event_id = (int)$_POST['event_id'];
    $title    = trim($_POST['title']);
    $order    = (int)$_POST['ordering'];
    $rule     = $_POST['advancement_rule'];
    $advance  = (int)$_POST['contestants_to_advance'];

    if ($rule === 'winner') $advance = 1;

    // Validate
    if ($rule === 'top_n') $check = validateAdvancement($conn, $event_id, $order, $advance);
    else $check = validateAdvancement($conn, $event_id, $order, 1);

    if ($check !== true) { header("Location: ../public/rounds.php?error=" . urlencode($check)); exit(); }

    // Duplicate Check
    $dupCheck = $conn->query("SELECT id FROM rounds WHERE event_id = $event_id AND ordering = $order");
    if ($dupCheck->num_rows > 0) { header("Location: ../public/rounds.php?error=Order #$order already exists."); exit(); }

    $stmt = $conn->prepare("INSERT INTO rounds (event_id, title, ordering, advancement_rule, contestants_to_advance, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("isisi", $event_id, $title, $order, $rule, $advance);
    
    if ($stmt->execute()) header("Location: ../public/rounds.php?success=Round added successfully");
    else header("Location: ../public/rounds.php?error=Failed to add round");
    exit();
}

// --- 2. UPDATE ROUND ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    requireRole('Event Manager');

    $round_id = (int)$_POST['round_id'];
    $evtCheck = $conn->query("SELECT event_id, status FROM rounds WHERE id = $round_id")->fetch_assoc();
    
    if ($evtCheck['status'] === 'Active') {
        header("Location: ../public/rounds.php?error=Cannot edit an Active round."); exit();
    }

    $event_id = $evtCheck['event_id'];
    $title    = trim($_POST['title']);
    $order    = (int)$_POST['ordering'];
    $rule     = $_POST['advancement_rule'];
    $advance  = (int)$_POST['contestants_to_advance'];

    if ($rule === 'winner') $advance = 1;

    $check = validateAdvancement($conn, $event_id, $order, $advance);
    if ($check !== true) { header("Location: ../public/rounds.php?error=" . urlencode($check)); exit(); }

    $stmt = $conn->prepare("UPDATE rounds SET title=?, ordering=?, advancement_rule=?, contestants_to_advance=? WHERE id=?");
    $stmt->bind_param("sisii", $title, $order, $rule, $advance, $round_id);

    if ($stmt->execute()) header("Location: ../public/rounds.php?success=Round updated");
    else header("Location: ../public/rounds.php?error=Update failed");
    exit();
}

// --- 3. DELETE ROUND (GET) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    requireRole('Event Manager');

    $id = (int)$_GET['id'];
    $check = $conn->query("SELECT status FROM rounds WHERE id = $id");
    if ($check->fetch_assoc()['status'] === 'Active') {
        header("Location: ../public/rounds.php?error=Cannot delete an Active round."); exit();
    }

    // Safety: Don't delete if scores exist
    $scoreCheck = $conn->prepare("SELECT id FROM scores WHERE round_id = ? LIMIT 1");
    $scoreCheck->bind_param("i", $id);
    $scoreCheck->execute();
    if ($scoreCheck->get_result()->num_rows > 0) {
        header("Location: ../public/rounds.php?error=Cannot delete: Scores exist."); exit();
    }

    $conn->query("DELETE FROM rounds WHERE id = $id");
    header("Location: ../public/rounds.php?success=Round deleted");
    exit();
}

//  PART B: TRAFFIC CONTROLLER (Returns JSON for JS Fetch)

// --- 4. START ROUND (Replaces set_active) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start') {
    requireRole('Event Manager');
    $round_id = (int)$_POST['round_id'];

    $conn->begin_transaction();
    try {
        // Fetch Context
        $r_query = $conn->query("SELECT event_id, ordering, title FROM rounds WHERE id = $round_id")->fetch_assoc();
        if (!$r_query) throw new Exception("Round not found.");
        $event_id = $r_query['event_id'];
        
        // RULE 1: CONCURRENCY CHECK (Single Thread)
        $check_active = $conn->query("SELECT title FROM rounds WHERE event_id = $event_id AND status = 'Active' AND id != $round_id");
        if ($check_active->num_rows > 0) {
            $active = $check_active->fetch_assoc();
            throw new Exception("TRAFFIC JAM: Cannot start '{$r_query['title']}'. '{$active['title']}' is currently LIVE.");
        }

        // RULE 2: INTEGRITY CHECK (Configuration)
        $config_msg = validateRoundConfiguration($conn, $round_id);
        if ($config_msg !== true) throw new Exception($config_msg);

        // RULE 3: GATEKEEPER CHECK (Qualified Candidates)
        $gate_msg = checkGatekeeper($conn, $event_id, $r_query['ordering']);
        if ($gate_msg !== true) throw new Exception($gate_msg);

        // ACTIVATE
        $conn->query("UPDATE rounds SET status = 'Active' WHERE id = $round_id");
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Round Started Successfully']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- 5. LOCK ROUND & PROMOTE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lock') {
    // Both Manager and Tabulator can lock
    $round_id = (int)$_POST['round_id'];

    // --- [NEW] SAFETY CHECK: Have all judges submitted? ---
    $evt_id_q = $conn->query("SELECT event_id FROM rounds WHERE id = $round_id")->fetch_assoc();
    $event_id = $evt_id_q['event_id'];

    // 1. Count Active Judges
    $j_total = $conn->query("SELECT COUNT(*) as cnt FROM event_judges WHERE event_id = $event_id AND status = 'Active' AND is_deleted = 0")->fetch_assoc()['cnt'];
    
    if ($j_total == 0) {
        echo json_encode(['status' => 'error', 'message' => "CANNOT LOCK: No active judges found for this event."]);
        exit;
    }

    // 2. Count Submitted Judges
    $j_sub = $conn->query("SELECT COUNT(*) as cnt FROM judge_round_status WHERE round_id = $round_id AND status = 'Submitted'")->fetch_assoc()['cnt'];

    if ($j_sub < $j_total) {
        $remaining = $j_total - $j_sub;
        echo json_encode(['status' => 'error', 'message' => "WAIT! $remaining judge(s) have not submitted their scores yet."]);
        exit;
    }

    $conn->begin_transaction();
    try {
        // A. Calculate Ranks
        $rankings = ScoreCalculator::calculate($round_id);
        
        // B. Get Limit
        $r_info = $conn->query("SELECT contestants_to_advance, event_id FROM rounds WHERE id = $round_id")->fetch_assoc();
        $limit = (int)$r_info['contestants_to_advance'];
        $event_id = $r_info['event_id'];

        // C. Clean Slate: Reset everyone to Eliminated first
        $conn->query("UPDATE contestant_details SET status = 'Eliminated' WHERE event_id = $event_id");

        // D. Promote Top N
        $promoted_count = 0;
        foreach ($rankings as $row) {
            if ($row['rank'] <= $limit) {
                $cid = $row['contestant']['detail_id'] ?? 0;
                if($cid > 0) {
                    $conn->query("UPDATE contestant_details SET status = 'Qualified' WHERE id = $cid");
                    $promoted_count++;
                }
            }
        }

        // E. Close Round
        $conn->query("UPDATE rounds SET status = 'Completed' WHERE id = $round_id");
        
        // F. Snapshot Results (Save history)
        $conn->query("DELETE FROM round_rankings WHERE round_id = $round_id");
        $stmt_snap = $conn->prepare("INSERT INTO round_rankings (round_id, contestant_id, total_score, `rank`) VALUES (?, ?, ?, ?)");
        
        foreach ($rankings as $row) {
            $cid = $row['contestant']['detail_id'] ?? 0;
            $score = str_replace(',', '', $row['final_score']); 
            $rank = $row['rank'];
            $stmt_snap->bind_param("iidi", $round_id, $cid, $score, $rank);
            $stmt_snap->execute();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Round Locked. Top $promoted_count contestants promoted."]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- 6. STOP ROUND (Emergency Reset) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'stop') {
    requireRole('Event Manager');
    $round_id = (int)$_POST['round_id'];

    // For this architecture, rely on the Guard.php to prevent data loss.
    $s_check = $conn->prepare("SELECT id FROM scores WHERE round_id = ? LIMIT 1");
    $s_check->bind_param("i", $round_id);
    $s_check->execute();
    if ($s_check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => "CANNOT STOP: Scores already exist. You must lock it."]);
        exit;
    }

    $conn->query("UPDATE rounds SET status = 'Pending' WHERE id = $round_id");
    echo json_encode(['status' => 'success', 'message' => "Round stopped and reset to Pending."]);
    exit;
}

// --- 7. RE-OPEN ROUND (Correction Mode) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reopen') {
    requireRole('Event Manager');
    $round_id = (int)$_POST['round_id'];

    $conn->begin_transaction();
    try {
        // 1. Check Context
        $r_query = $conn->query("SELECT event_id, ordering, title FROM rounds WHERE id = $round_id")->fetch_assoc();
        $event_id = $r_query['event_id'];
        $order = $r_query['ordering'];

        // 2. Safety Check: Can we re-open?
        // Cannot re-open Round 1 if Round 2 is already Active/Completed.
        $next_round_check = $conn->query("SELECT title FROM rounds WHERE event_id = $event_id AND ordering > $order AND status != 'Pending'");
        if ($next_round_check->num_rows > 0) {
            $next = $next_round_check->fetch_assoc();
            throw new Exception("CANNOT RE-OPEN: The next round '{$next['title']}' has already started. You must reset that one first.");
        }

        // 3. The Re-Open Action
        // Set status back to 'Active'. 
        // Do NOT delete scores (that's the point, we want to keep them).
        // Do NOT delete 'Qualified' tags yet (they will be overwritten when Re-Lock).
        $conn->query("UPDATE rounds SET status = 'Active' WHERE id = $round_id");

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Round Re-Opened. You can now adjust settings or scores, then Re-Lock."]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>