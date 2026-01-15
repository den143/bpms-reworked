<?php
// bpms/public/live_screen.php
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();

if (!in_array($_SESSION['role'], ['Event Manager', 'Tabulator', 'Judge Coordinator'])) {
    die("Access Denied");
}

require_once __DIR__ . '/../app/config/database.php';

$uid = $_SESSION['user_id'];
$event_title = "BPMS Live";
$event_id = 0;

// 1. Fetch Event
if ($_SESSION['role'] === 'Event Manager') {
    $stmt = $conn->prepare("SELECT id, title FROM events WHERE manager_id = ? AND status = 'Active' LIMIT 1");
    $stmt->bind_param("i", $uid);
} else {
    $stmt = $conn->prepare("
        SELECT e.id, e.title 
        FROM events e 
        JOIN event_teams et ON e.id = et.event_id 
        WHERE et.user_id = ? AND et.status = 'Active' AND e.status = 'Active' LIMIT 1");
    $stmt->bind_param("i", $uid);
}
$stmt->execute();
$evt = $stmt->get_result()->fetch_assoc();

if ($evt) {
    $event_id = $evt['id'];
    $event_title = $evt['title'];
} else {
    die("No Active Event Found.");
}

// 2. Fetch Rounds
$r_stmt = $conn->prepare("SELECT id, title, ordering, qualify_count, type FROM rounds WHERE event_id = ? AND is_deleted = 0 ORDER BY ordering");
$r_stmt->bind_param("i", $event_id);
$r_stmt->execute();
$rounds = $r_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. AJAX Data Handler
if (isset($_GET['fetch_rows']) && isset($_GET['round_id'])) {
    $selected_round_id = $_GET['round_id'];
    $mode = $_GET['mode'] ?? 'list';

    // Get Round Details & STATUS
    $rd_stmt = $conn->prepare("SELECT ordering, qualify_count, type, status FROM rounds WHERE id = ?");
    $rd_stmt->bind_param("i", $selected_round_id);
    $rd_stmt->execute();
    $rd = $rd_stmt->get_result()->fetch_assoc();
    
    $limit = $rd['qualify_count'];
    $is_final = ($rd['type'] === 'Final');
    $round_order = $rd['ordering'];
    $round_status = $rd['status'];

    // CHECK: IS ROUND LOCKED?
    if ($mode === 'rank' && $round_status !== 'Completed') {
        echo "<tr>
                <td colspan='3' style='text-align:center; padding:100px;'>
                    <div style='color: #dc2626; font-size: 3em; margin-bottom: 10px;'><i class='fas fa-lock'></i></div>
                    <div style='font-size: 2em; font-weight: bold; color: #374151;'>RESULTS HIDDEN</div>
                    <div style='font-size: 1.2em; color: #6b7280; margin-top: 10px;'>
                        This round is currently <strong>" . htmlspecialchars($round_status) . "</strong>.<br>
                        The Tabulator must <strong>LOCK</strong> the round before results can be viewed.
                    </div>
                </td>
              </tr>";
        exit;
    }

    $order_clause = ($mode === 'list') ? "ec.contestant_number ASC" : "final_score DESC";
    $join_type = ($round_order == 1) ? "LEFT JOIN" : "INNER JOIN";

    $sql = "
        SELECT 
            ec.id,
            u.name, 
            ec.photo, 
            ec.contestant_number, 
            COALESCE(AVG(judge_sub.judge_total), 0) as final_score
        FROM event_contestants ec
        JOIN users u ON ec.user_id = u.id
        $join_type (
            SELECT 
                s.contestant_id,
                s.judge_id,
                SUM(s.score_value * (seg.weight_percent / 100.0)) as judge_total
            FROM scores s
            JOIN criteria c ON s.criteria_id = c.id
            JOIN segments seg ON c.segment_id = seg.id
            WHERE seg.round_id = ?
            GROUP BY s.contestant_id, s.judge_id
        ) judge_sub ON ec.id = judge_sub.contestant_id
        WHERE ec.event_id = ? AND ec.is_deleted = 0
        GROUP BY ec.id
        ORDER BY $order_clause
    ";

    $q_stmt = $conn->prepare($sql);
    $q_stmt->bind_param("ii", $selected_round_id, $event_id);
    $q_stmt->execute();
    $rankings = $q_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($rankings)) {
        echo "<tr><td colspan='3' style='text-align:center; padding:50px; color:#999; font-size:1.5em;'>No contestants found for this round.</td></tr>";
    } else {
        $rank = 1;
        foreach($rankings as $row) {
            $score = number_format($row['final_score'], 2);
            $name = htmlspecialchars($row['name']);
            $num = $row['contestant_number'];
            $photo = htmlspecialchars($row['photo']);
            
            // --- LOGIC: Highlight Winners ---
            $rowClass = "";
            
            if ($mode === 'rank') {
                if ($rank <= $limit) {
                    if ($is_final && $rank == 1) {
                        $rowClass = "row-winner"; 
                    } else {
                        $rowClass = "row-qualified"; 
                    }
                } else {
                    $rowClass = "row-eliminated"; 
                }
            }

            echo "<tr class='{$rowClass}'>
                    <td class='rank-col'>";
            
            if($mode === 'rank') {
                echo "#" . $rank;
            } else {
                echo "<i class='fas fa-user' style='color:#ccc;'></i>";
            }
            
            echo "  </td>
                    <td>
                        <img src='./assets/uploads/contestants/{$photo}' class='c-img' onerror=\"this.src='./assets/images/default_user.png'\">
                        <div style='display:inline-block; vertical-align:middle;'>
                            <div style='font-size:0.9em; color:#6b7280; font-weight:bold;'>CANDIDATE #{$num}</div>
                            <div>{$name}</div>
                        </div>
                    </td>
                    <td style='text-align:right;'>";
            
            if($mode === 'rank') {
                echo "<span class='score-display'>{$score}</span>";
            } else {
                echo "<span style='color:#ccc; font-style:italic; font-size:0.8em;'>--</span>";
            }

            echo "  </td>
                  </tr>";
            $rank++;
        }
    }
    exit; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Results - <?= htmlspecialchars($event_title) ?></title>
    
    <link rel="stylesheet" href="./assets/css/style.css"> 
    <link rel="stylesheet" href="./assets/fontawesome/css/all.min.css">

    <style>
        /* --- BACKGROUND: CLEAN & PLAIN --- */
        body { 
            background-color: #f4f6f9;
            font-family: 'Segoe UI', sans-serif; 
            height: 100vh; 
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center; 
            overflow: hidden;
        }
        
        /* Container Card - MAXIMIZED */
        .live-container { 
            position: relative; 
            width: 95%; 
            max-width: 1400px; 
            height: 92vh; 
            background: white; 
            padding: 15px 15px 0 15px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex; 
            flex-direction: column;
            border: 1px solid #e5e7eb;
        }

        /* Fullscreen Overrides */
        body:fullscreen { padding: 0; background: #fff; display: block; }
        body:fullscreen .live-container {
            width: 100%; max-width: 100%; height: 100vh;
            border-radius: 0; padding: 20px; box-shadow: none; border: none;
        }

        /* Header */
        .header-section { text-align: center; margin-bottom: 10px; border-bottom: 2px solid #F59E0B; padding-bottom: 10px; }
        .header-section h1 { margin: 0; color: #1f2937; font-size: 2.2em; text-transform: uppercase; }
        .header-section p { color: #6b7280; margin-top: 5px; font-size: 1.1em; }

        /* Controls Panel */
        .controls-panel {
            display: flex; justify-content: space-between; align-items: center;
            background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;
            margin-bottom: 10px; flex-wrap: wrap; gap: 10px; flex-shrink: 0;
        }

        /* Buttons & Selects */
        .mode-group { display: flex; gap: 10px; }
        .btn-mode {
            padding: 8px 16px; border: 1px solid #cbd5e1; background: white; 
            border-radius: 6px; cursor: pointer; font-weight: 600; color: #64748b;
            transition: 0.2s; display: flex; align-items: center; gap: 8px;
        }
        .btn-mode:hover { background: #f1f5f9; }
        .btn-mode.active { background: #3b82f6; color: white; border-color: #3b82f6; box-shadow: 0 2px 5px rgba(59, 130, 246, 0.3); }

        .btn-fs { padding: 8px 12px; background: #1f2937; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .btn-fs:hover { background: #374151; }

        select { padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; min-width: 250px; font-size: 16px; }

        /* Scrollable Results Area */
        #resultsArea { flex-grow: 1; overflow-y: auto; padding-bottom: 0; }
        #resultsArea::-webkit-scrollbar { width: 8px; }
        #resultsArea::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }

        /* Table Styling */
        .ranking-table { width: 100%; border-collapse: separate; border-spacing: 0 5px; margin-top: 0; }
        .ranking-table th { background: #1f2937; color: white; padding: 15px; text-align: left; font-size: 1.1em; position: sticky; top: 0; z-index: 10; }
        .ranking-table td { padding: 15px; background: #fff; border-bottom: 1px solid #eee; vertical-align: middle; font-size: 1.2em; transition: 0.3s; }
        
        /* --- WINNER / QUALIFIED STYLES --- */
        .row-qualified td { background-color: #ecfdf5; border-bottom: 2px solid #10b981; }
        .row-winner td { background-color: #fffbeb; border-bottom: 2px solid #f59e0b; font-weight: bold; }
        
        /* --- ELIMINATED STYLE --- */
        .row-eliminated td { opacity: 0.5; background-color: #f9fafb; filter: grayscale(1); }

        /* --- RANK COLUMN STYLING (THE GOLD NUMBERS) --- */
        .rank-col { 
            width: 80px; 
            text-align: center; 
            font-size: 1.4em; 
            color: #94a3b8; /* Default Grey */
            font-weight: bold;
        }

        /* If Qualified or Winner, make Rank GOLD and BIGGER */
        .row-qualified .rank-col, 
        .row-winner .rank-col {
            color: #d97706; /* Dark Gold/Orange */
            font-size: 2em; /* Bigger */
            font-weight: 900; /* Extra Bold */
            text-shadow: 1px 1px 0 rgba(255,255,255,0.8);
        }

        /* Winner gets slightly bigger Gold */
        .row-winner .rank-col {
            color: #b45309; /* Deep Gold */
            font-size: 2.2em;
        }

        .c-img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0; margin-right: 15px; }
        
        .score-display { font-family: 'Segoe UI', sans-serif; font-weight: 800; font-size: 1.4em; color: #1f2937; }
        
        .status-bar { text-align: center; padding: 8px; font-weight: bold; font-size: 0.9em; border-radius: 4px; margin-bottom: 5px; flex-shrink: 0; }
        .status-unofficial { background: #e2e8f0; color: #64748b; }
        .status-live { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body>

    <div class="live-container">
        
        <div class="header-section">
            <h1><?= htmlspecialchars($event_title) ?></h1>
            <p>Official Tally Board</p>
        </div>

        <div class="controls-panel">
            <select id="roundSelect" onchange="resetView()">
                <option value="" disabled selected>-- Select Round --</option>
                <?php foreach($rounds as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['title']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="mode-group">
                <button class="btn-mode active" id="btnList" onclick="setMode('list')">
                    <i class="fas fa-list"></i> Candidates
                </button>
                
                <button class="btn-mode" id="btnRank" onclick="setMode('rank')">
                    <i class="fas fa-flag-checkered"></i> Show Final Result
                </button>

                <button onclick="toggleFullScreen()" class="btn-fs" title="Fullscreen">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
        </div>

        <div id="statusIndicator" class="status-bar status-unofficial">
            SELECT A ROUND
        </div>

        <div id="resultsArea">
            <table class="ranking-table">
                <thead>
                    <tr>
                        <th width="10%" style="text-align:center;">#</th>
                        <th width="70%">Candidate Info</th>
                        <th width="20%" style="text-align:right;">Score</th>
                    </tr>
                </thead>
                <tbody id="scoreBody">
                    <tr><td colspan="3" style="text-align:center; padding:50px; color:#9ca3af;">Waiting for selection...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        let currentRoundId = null;
        let currentMode = 'list';
        let pollTimer = null;

        function toggleFullScreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(e => alert(e.message));
            } else {
                if (document.exitFullscreen) document.exitFullscreen();
            }
        }

        function resetView() {
            const select = document.getElementById('roundSelect');
            currentRoundId = select.value;
            setMode('list'); // Default to list
        }

        function setMode(mode) {
            if(!currentRoundId) {
                alert("Please select a round first.");
                return;
            }
            currentMode = mode;

            document.getElementById('btnList').className = mode === 'list' ? 'btn-mode active' : 'btn-mode';
            document.getElementById('btnRank').className = mode === 'rank' ? 'btn-mode active' : 'btn-mode';

            const statusEl = document.getElementById('statusIndicator');
            if(mode === 'list') {
                statusEl.innerText = "OFFICIAL CANDIDATES (ALPHABETICAL ORDER)";
                statusEl.className = "status-bar status-unofficial";
            } else {
                statusEl.innerText = "FINAL RESULTS";
                statusEl.className = "status-bar status-live";
            }

            fetchData();
            if(pollTimer) clearInterval(pollTimer);
            pollTimer = setInterval(fetchData, 3000);
        }

        function fetchData() {
            if(!currentRoundId) return;
            fetch(`live_screen.php?round_id=${currentRoundId}&fetch_rows=1&mode=${currentMode}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('scoreBody').innerHTML = html;
                })
                .catch(err => console.error('Error:', err));
        }
    </script>
</body>
</html>