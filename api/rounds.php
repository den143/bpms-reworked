<?php
// bpms/api/rounds.php
// COMBINED: Configuration (CRUD) + Traffic Controller (State Machine)

require_once __DIR__ . '/../app/core/guard.php';
requireLogin(); 
requireRole(['Event Manager', 'Tabulator']); // Allow Tabulator to Lock, but only Manager to Configure
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/ScoreCalculator.php';

// HELPER FUNCTIONS (VALIDATION LOGIC)

// 1. Validate "Funnel Logic" (Configuration)
// Ensures you don't accidentally qualify more people than existed in the previous round.
function validateAdvancement($conn, $event_id, $current_order, $current_qualify_count) {
    if ($current_qualify_count < 1) return "Invalid Number: You must advance at least 1 contestant.";

    $prev_order = $current_order - 1;
    // If it's the first round, no previous limits apply
    if ($prev_order < 1) return true; 

    // Fetch previous round info from 'rounds' table
    $stmt = $conn->prepare("SELECT qualify_count, type, title FROM rounds WHERE event_id = ? AND ordering = ?");
    $stmt->bind_param("ii", $event_id, $prev_order);
    $stmt->execute();
    $prev = $stmt->get_result()->fetch_assoc();

    if ($prev) {
        $prev_n = (int)$prev['qualify_count'];
        
        // If previous round was a 'Final', the event should have ended.
        if ($prev['type'] === 'Final') {
            return "Action Denied: The previous round '{$prev['title']}' was a Final round. You cannot add more rounds after it.";
        }
        // Funnel Logic: You can't qualify 10 people if only 5 remained.
        if ($current_qualify_count >= $prev_n) {
            return "Invalid Configuration: Qualifiers ($current_qualify_count) must be LESS than previous round ($prev_n).";
        }
    }
    return true;
}

// 2. Strict Configuration Check (Judges, Contestants, 100% Weights, 100pts Criteria)
function validateRoundConfiguration($conn, $round_id, $event_id) {
    
    // A. REQUIRE REGISTERED JUDGES
    $j_check = $conn->query("SELECT COUNT(*) as cnt FROM event_judges WHERE event_id = $event_id AND status = 'Active' AND is_deleted = 0")->fetch_assoc();
    if ($j_check['cnt'] == 0) {
        return "Cannot Start: No active judges have been assigned to this event.";
    }

    // B. REQUIRE REGISTERED CONTESTANTS
    $c_check = $conn->query("SELECT COUNT(*) as cnt FROM event_contestants WHERE event_id = $event_id AND status IN ('Active', 'Qualified') AND is_deleted = 0")->fetch_assoc();
    if ($c_check['cnt'] == 0) {
        return "Cannot Start: There are no active contestants registered for this event.";
    }

    // C. CHECK ROUND WEIGHT (Must be exactly 100%)
    $stmt = $conn->prepare("SELECT SUM(weight_percent) as total FROM segments WHERE round_id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $round_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $seg_total = (float)($res['total'] ?? 0);

    // Use floating point comparison (allow tiny margin for 99.999...)
    if (abs($seg_total - 100.00) > 0.001) {
        return "Cannot Start: Total Segment Weight is $seg_total%. It must be exactly 100%.";
    }

    // D. CHECK CRITERIA POINTS (Must be exactly 100 per segment)
    // FIX: Moved 'c.is_deleted = 0' to ON clause so segments with NO criteria are not hidden
    $sql = "SELECT s.title, COALESCE(SUM(c.max_score), 0) as criteria_total 
            FROM segments s 
            LEFT JOIN criteria c ON s.id = c.segment_id AND c.is_deleted = 0
            WHERE s.round_id = ? AND s.is_deleted = 0
            GROUP BY s.id";
            
    $stmt2 = $conn->prepare($sql);
    $stmt2->bind_param("i", $round_id);
    $stmt2->execute();
    $result = $stmt2->get_result();

    if ($result->num_rows === 0) {
        return "Cannot Start: No segments found for this round.";
    }

    while ($row = $result->fetch_assoc()) {
        $score = (float)$row['criteria_total'];
        if (abs($score - 100.00) > 0.001) {
            return "Cannot Start: Segment '{$row['title']}' criteria total is $score. It must be exactly 100.";
        }
    }

    return true;
}

// 3. The Gatekeeper Check (Qualified Candidates)
function checkGatekeeper($conn, $event_id, $round_ordering) {
    // If this is Round 2, 3, etc... we must have 'Qualified' people waiting.
    if ($round_ordering > 1) {
        // Matches 'event_contestants' table
        $q_check = $conn->query("SELECT COUNT(*) as cnt FROM event_contestants WHERE event_id = $event_id AND status = 'Qualified'")->fetch_assoc();
        if ($q_check['cnt'] == 0) {
            return "EMPTY ROSTER: This is Round $round_ordering, but no contestants are marked as 'Qualified'. Did you forget to lock the previous round?";
        }
    }
    return true;
}

// PART A: CRUD OPERATIONS (Returns Redirects for Forms)

// --- 1. ADD ROUND ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    requireRole('Event Manager'); // Strict Role Check

    $event_id = (int)$_POST['event_id'];
    $title    = trim($_POST['title']);
    $order    = (int)$_POST['ordering'];
    $type     = $_POST['type']; // 'Elimination' or 'Final'
    $qualify  = (int)$_POST['qualify_count'];

    // If Final, usually only 1 winner, but let's trust the input or force 1
    if ($type === 'Final') $qualify = 1;

    // Validate
    $check = validateAdvancement($conn, $event_id, $order, $qualify);
    if ($check !== true) { header("Location: ../public/rounds.php?error=" . urlencode($check)); exit(); }

    // Duplicate Check
    $dupCheck = $conn->query("SELECT id FROM rounds WHERE event_id = $event_id AND ordering = $order");
    if ($dupCheck->num_rows > 0) { header("Location: ../public/rounds.php?error=Order #$order already exists."); exit(); }

    // Matches 'rounds' table structure
    $stmt = $conn->prepare("INSERT INTO rounds (event_id, title, ordering, type, qualify_count, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("isisi", $event_id, $title, $order, $type, $qualify);
    
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
    $type     = $_POST['type'];
    $qualify  = (int)$_POST['qualify_count'];

    if ($type === 'Final') $qualify = 1;

    $check = validateAdvancement($conn, $event_id, $order, $qualify);
    if ($check !== true) { header("Location: ../public/rounds.php?error=" . urlencode($check)); exit(); }

    $stmt = $conn->prepare("UPDATE rounds SET title=?, ordering=?, type=?, qualify_count=? WHERE id=?");
    $stmt->bind_param("sisii", $title, $order, $type, $qualify, $round_id);

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

    // Safety: Don't delete if scores exist. 
    // Scores link to Criteria -> Segments -> Round
    $scoreCheck = $conn->prepare("
        SELECT sc.id FROM scores sc
        JOIN criteria c ON sc.criteria_id = c.id
        JOIN segments s ON c.segment_id = s.id
        WHERE s.round_id = ? LIMIT 1
    ");
    $scoreCheck->bind_param("i", $id);
    $scoreCheck->execute();
    
    if ($scoreCheck->get_result()->num_rows > 0) {
        header("Location: ../public/rounds.php?error=Cannot delete: Scores exist."); exit();
    }

    // We use soft delete based on DB structure
    $conn->query("UPDATE rounds SET is_deleted = 1 WHERE id = $id");
    header("Location: ../public/rounds.php?success=Round deleted");
    exit();
}

// PART B: TRAFFIC CONTROLLER (Returns JSON for JS Fetch)

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
        $config_msg = validateRoundConfiguration($conn, $round_id, $event_id);
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
    $round_id = (int)$_POST['round_id'];

    // --- SAFETY CHECK: Judges ---
    $evt_id_q = $conn->query("SELECT event_id FROM rounds WHERE id = $round_id")->fetch_assoc();
    $event_id = $evt_id_q['event_id'];

    $j_total = $conn->query("SELECT COUNT(*) as cnt FROM event_judges WHERE event_id = $event_id AND status = 'Active' AND is_deleted = 0")->fetch_assoc()['cnt'];
    if ($j_total == 0) {
        echo json_encode(['status' => 'error', 'message' => "CANNOT LOCK: No active judges found."]);
        exit;
    }

    $j_sub = $conn->query("SELECT COUNT(*) as cnt FROM judge_round_status WHERE round_id = $round_id AND status = 'Submitted'")->fetch_assoc()['cnt'];
    if ($j_sub < $j_total) {
        $remaining = $j_total - $j_sub;
        echo json_encode(['status' => 'error', 'message' => "WAIT! $remaining judge(s) have not submitted scores."]);
        exit;
    }

    $conn->begin_transaction();
    try {
        // A. Calculate Ranks
        $rankings = ScoreCalculator::calculate($round_id);
        
        // B. Context Info
        $r_info = $conn->query("SELECT qualify_count, event_id, ordering FROM rounds WHERE id = $round_id")->fetch_assoc();
        $limit = (int)$r_info['qualify_count'];
        $event_id = $r_info['event_id'];

        // C. Clean Slate: Reset statuses
        $conn->query("UPDATE event_contestants SET status = 'Eliminated' WHERE event_id = $event_id AND status IN ('Active', 'Qualified')");

        // D. INSERT SCORES & Promote Top N
        // [FIX] Prepare statement for saving results
        $save_stmt = $conn->prepare("INSERT INTO round_rankings (round_id, contestant_id, total_score, `rank`) VALUES (?, ?, ?, ?)");
        
        $promoted_count = 0;
        foreach ($rankings as $row) {
            $cid = $row['contestant']['detail_id'] ?? $row['contestant']['id'] ?? 0; // Robust ID fetch
            $score = (float)$row['raw_score'];
            $rank = (int)$row['rank'];

            // 1. Save to Database (The missing step!)
            if ($cid > 0) {
                $save_stmt->bind_param("iidi", $round_id, $cid, $score, $rank);
                $save_stmt->execute();
            }

            // 2. Promote if qualified
            if ($rank <= $limit && $cid > 0) {
                $conn->query("UPDATE event_contestants SET status = 'Qualified' WHERE id = $cid");
                $promoted_count++;
            }
        }

        // E. Close Round
        $conn->query("UPDATE rounds SET status = 'Completed' WHERE id = $round_id");
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Round Locked. Scores Saved. Top $promoted_count promoted."]);

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

    // Check for scores via JOIN
    $s_check = $conn->prepare("
        SELECT sc.id FROM scores sc
        JOIN criteria c ON sc.criteria_id = c.id
        JOIN segments s ON c.segment_id = s.id
        WHERE s.round_id = ? LIMIT 1
    ");
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
        $next_round_check = $conn->query("SELECT title FROM rounds WHERE event_id = $event_id AND ordering > $order AND status != 'Pending' AND is_deleted = 0");
        if ($next_round_check->num_rows > 0) {
            $next = $next_round_check->fetch_assoc();
            throw new Exception("CANNOT RE-OPEN: The next round '{$next['title']}' has already started. You must reset that one first.");
        }

        // 3. The Re-Open Action
        // Set status back to 'Active'. 
        // We do NOT delete scores (we want to fix them).
        $conn->query("UPDATE rounds SET status = 'Active' WHERE id = $round_id");

        // 4. Reset Contestant Status
        // Since we are re-opening, we don't know who qualifies yet. 
        // Reset everyone involved to 'Active' so they aren't prematurely eliminated/qualified.
        // (Assuming this is a re-judge situation)
        $conn->query("UPDATE event_contestants SET status = 'Active' WHERE event_id = $event_id AND status IN ('Qualified', 'Eliminated')");

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Round Re-Opened. Statuses reset. You can now adjust scores."]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>