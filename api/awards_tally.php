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
    
    // Logic: Delegate complex calculation to the Model
    $data = AwardCalculator::getAwardsList((int)$_GET['event_id']);
    
    // Logic: Fetch all contestants (ID & Name) to populate the "Manual Selection" dropdowns in the UI.
    $cont_sql = "SELECT cd.id, u.name FROM contestant_details cd JOIN users u ON cd.user_id = u.id WHERE cd.event_id = " . (int)$_GET['event_id'];
    $contestants = $conn->query($cont_sql)->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['status' => 'success', 'awards' => $data, 'contestants' => $contestants]);
    exit();
}

// 2. POST REQUEST: SAVE MANUAL WINNER
// Purpose: Assigns a specific contestant to an award (e.g., "Best in Talent").
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode JSON input from the frontend
    $input = json_decode(file_get_contents('php://input'), true);
    $award_id = (int)$input['award_id'];
    $contestant_id = (int)$input['contestant_id']; // This is the Contestant Detail ID
    $event_id = (int)$input['event_id'];

    if (!$award_id || !$contestant_id) {
        echo json_encode(['error' => 'Invalid Input']);
        exit();
    }

    // LOGIC: UPSERT (Update if exists, Insert if new)
    // If a winner is already assigned for this award, we update it. If not, we insert a new row.
    $stmt = $conn->prepare("INSERT INTO award_winners (award_id, contestant_id, event_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE contestant_id = VALUES(contestant_id)");
    $stmt->bind_param("iii", $award_id, $contestant_id, $event_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['error' => 'Database error']);
    }
    exit();
}
?>