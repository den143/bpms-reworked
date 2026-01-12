<?php
// Purpose: API for managing Award Results. 
// Handles fetching calculated winners (GET) and saving manual award assignments (POST).

header('Content-Type: application/json');
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/AwardCalculator.php';

// 1. GET REQUEST: FETCH AWARDS DATA
// Purpose: Returns the calculated standings and a list of contestants for dropdowns.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['event_id'])) {
        echo json_encode(['error' => 'Event ID required']);
        exit();
    }
    
    $event_id = (int)$_GET['event_id'];

    // Logic: Delegate complex calculation to the Model (AwardCalculator needs update in Batch 3)
    $data = AwardCalculator::getAwardsList($event_id);
    
    // Logic: Fetch all contestants (ID & Name) to populate the "Manual Selection" dropdowns in the UI.
    $cont_sql = "SELECT ec.id, u.name, ec.contestant_number 
                 FROM event_contestants ec 
                 JOIN users u ON ec.user_id = u.id 
                 WHERE ec.event_id = ? 
                 AND ec.is_deleted = 0 
                 ORDER BY ec.contestant_number ASC";
                 
    $stmt = $conn->prepare($cont_sql);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $contestants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['status' => 'success', 'awards' => $data, 'contestants' => $contestants]);
    exit();
}

// 2. POST REQUEST: SAVE MANUAL WINNER
// Purpose: Assigns a specific contestant to an award (e.g., "Best in Talent").
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode JSON input from the frontend
    $input = json_decode(file_get_contents('php://input'), true);
    $award_id = (int)$input['award_id'];
    $contestant_id = (int)$input['contestant_id']; // This is event_contestants.id

    if (!$award_id || !$contestant_id) {
        echo json_encode(['error' => 'Invalid Input']);
        exit();
    }

    // LOGIC: Replace Previous Winner
    // To ensure only ONE winner per award (for manual selection), we Delete then Insert.
    
    $conn->begin_transaction();
    try {
        // 1. Clear existing 'Winner' for this award
        $del = $conn->prepare("DELETE FROM award_winners WHERE award_id = ? AND title_label = 'Winner'");
        $del->bind_param("i", $award_id);
        $del->execute();

        // 2. Insert the new winner
        $stmt = $conn->prepare("INSERT INTO award_winners (award_id, contestant_id, title_label) VALUES (?, ?, 'Winner')");
        $stmt->bind_param("ii", $award_id, $contestant_id);
        $stmt->execute();

        $conn->commit();
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}
?>