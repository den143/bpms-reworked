<?php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();       // Ensures they have a Ticket ID (stored in $_SESSION['user_id'])
requireRole('Audience');

require_once __DIR__ . '/../app/config/database.php';

$ticket_id = $_SESSION['user_id'];

// 1. Get Event Context from Ticket
$evt_stmt = $conn->prepare("SELECT event_id FROM tickets WHERE id = ?");
$evt_stmt->bind_param("i", $ticket_id);
$evt_stmt->execute();
$ticket_meta = $evt_stmt->get_result()->fetch_assoc();
$event_id = $ticket_meta['event_id'];

// 2. Get Vote Status
$v_stmt = $conn->prepare("SELECT contestant_id FROM audience_votes WHERE ticket_id = ? LIMIT 1");
$v_stmt->bind_param("i", $ticket_id);
$v_stmt->execute();
$vote_record = $v_stmt->get_result()->fetch_assoc();

$has_voted = ($vote_record !== null);
$voted_for_id = $has_voted ? $vote_record['contestant_id'] : null;

// 3. Handle Voting
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote_id'])) {
    if (!$has_voted) {
        $vote_id = intval($_POST['vote_id']);
        
        // Transaction: Record Vote AND Mark Ticket as Used
        $conn->begin_transaction();
        try {
            // A. Insert Vote
            $stmt_insert = $conn->prepare("INSERT INTO audience_votes (ticket_id, contestant_id) VALUES (?, ?)");
            $stmt_insert->bind_param("ii", $ticket_id, $vote_id);
            $stmt_insert->execute();

            // B. Mark Ticket as Used
            $stmt_update = $conn->prepare("UPDATE tickets SET status = 'Used' WHERE id = ?");
            $stmt_update->bind_param("i", $ticket_id);
            $stmt_update->execute();

            $conn->commit();
            
            $has_voted = true;
            $voted_for_id = $vote_id;
            $msg = "Vote successfully cast! Thank you.";

        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Error processing vote. Please try again.";
        }
    }
}

// 4. Fetch Contestants for this specific Event
// UPDATED: Added 'ec.status' to selection and included 'Eliminated' in the WHERE clause
$sql = "SELECT ec.id, ec.contestant_number, ec.photo, ec.age, ec.hometown, ec.motto, ec.status, u.name 
        FROM event_contestants ec 
        JOIN users u ON ec.user_id = u.id 
        WHERE ec.event_id = ? 
          AND ec.status IN ('Active', 'Qualified', 'Eliminated') 
          AND ec.is_deleted = 0
        ORDER BY ec.contestant_number ASC";

$stmt_c = $conn->prepare($sql);
$stmt_c->bind_param("i", $event_id);
$stmt_c->execute();
$contestants = $stmt_c->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audience Voting | BPMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding-bottom: 50px; }
        
        /* Navbar */
        .navbar { background: #111827; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .logo { font-weight: 800; font-size: 20px; color: #F59E0B; }
        .btn-logout { background: transparent; border: 1px solid #ef4444; color: #ef4444; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 14px; font-weight: bold; transition: 0.3s; }
        .btn-logout:hover { background: #ef4444; color: white; }

        .container { max-width: 1000px; margin: 30px auto; padding: 0 15px; }

        /* Messages */
        .status-box { background: white; padding: 25px; border-radius: 12px; text-align: center; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .success-msg { background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; border: 1px solid #a7f3d0; }

        /* Grid */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 25px; }
        
        /* Card */
        .card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.2s; position: relative; }
        .card:hover { transform: translateY(-5px); }
        
        /* Number Badge */
        .number-badge { position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,0.7); color: white; padding: 5px 12px; border-radius: 20px; font-weight: bold; font-size: 14px; backdrop-filter: blur(2px); z-index: 10; }

        /* Status Badge */
        .status-badge { position: absolute; top: 10px; right: 10px; padding: 4px 10px; border-radius: 15px; font-weight: 700; font-size: 11px; text-transform: uppercase; z-index: 10; letter-spacing: 0.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .tag-finalist { background: #F59E0B; color: white; border: 1px solid #d97706; }

        .photo { width: 100%; height: 280px; object-fit: cover; background: #e5e7eb; }
        .info { padding: 20px; text-align: center; }
        .name { font-size: 18px; font-weight: 700; color: #1f2937; margin-bottom: 5px; }
        .motto { font-size: 13px; color: #6b7280; font-style: italic; margin-bottom: 15px; display: block; }
        .stats { font-size: 12px; color: #9ca3af; margin-bottom: 15px; border-top: 1px solid #f3f4f6; padding-top: 10px; }

        /* Buttons */
        .btn-vote { width: 100%; padding: 12px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; background: #111827; color: white; font-size: 14px; }
        .btn-vote:hover { background: #F59E0B; }
        
        /* Disabled State */
        .voted-btn { background: #e5e7eb; color: #9ca3af; cursor: not-allowed; }
        .voted-badge { background: #059669; color: white; padding: 12px; border-radius: 8px; font-weight: bold; display: block; }
        .locked-badge { background: #9ca3af; color: white; padding: 12px; border-radius: 8px; font-weight: bold; display: block; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="logo"><i class="fas fa-crown"></i> BPMS Audience</div>
        <a href="logout.php" class="btn-logout" onclick="return confirm('Warning: Logging out will invalidate your session. Continue?');">
            Logout <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>

    <div class="container">
        
        <?php if ($msg): ?>
            <div class="success-msg"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
        <?php endif; ?>

        <div class="status-box">
            <?php if ($has_voted): ?>
                <h2 style="color: #059669;">Vote Recorded</h2>
                <p>You have successfully cast your vote. You may browse the candidates, but voting is now closed for this ticket.</p>
            <?php else: ?>
                <h2 style="color: #1f2937;">Cast Your Vote</h2>
                <p>Select your favorite contestant below. <strong>Note: Your ticket allows only ONE vote.</strong></p>
            <?php endif; ?>
        </div>

        <div class="grid">
            <?php if ($contestants && $contestants->num_rows > 0): ?>
                <?php while($row = $contestants->fetch_assoc()): ?>
                    <div class="card">
                        <div class="number-badge">#<?= htmlspecialchars($row['contestant_number']) ?></div>
                        
                        <?php if ($row['status'] === 'Qualified'): ?>
                            <div class="status-badge tag-finalist"><i class="fas fa-star"></i> FINALIST</div>
                        <?php endif; ?>

                        <img src="./assets/uploads/contestants/<?= htmlspecialchars($row['photo']) ?>" class="photo" alt="Contestant">
                        
                        <div class="info">
                            <div class="name"><?= htmlspecialchars($row['name']) ?></div>
                            <span class="motto">"<?= htmlspecialchars($row['motto']) ?>"</span>
                            
                            <div class="stats">
                                <?= htmlspecialchars($row['age']) ?> yrs old • <?= htmlspecialchars($row['hometown']) ?>
                            </div>

                            <?php if (!$has_voted): ?>
                                <form method="POST" onsubmit="return confirm('Confirm vote for <?= htmlspecialchars($row['name']) ?>? This cannot be undone.');">
                                    <input type="hidden" name="vote_id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn-vote">Vote Now</button>
                                </form>
                            <?php else: ?>
                                <?php if ($voted_for_id == $row['id']): ?>
                                    <div class="voted-badge">YOU VOTED <i class="fas fa-check"></i></div>
                                <?php else: ?>
                                    <div class="locked-badge">LOCKED</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align:center; color:#6b7280; grid-column: 1 / -1;">No official candidates found for this event.</p>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>