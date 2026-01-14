<?php
// bpms/public/print_report.php - Logo Added & CSS Removed
require_once __DIR__ . '/../app/core/guard.php';
requireLogin();
require_once __DIR__ . '/../app/models/ReportModel.php';

// 1. Get Event Context
if (!isset($_GET['event_id'])) {
    require_once __DIR__ . '/../app/config/database.php';
    $chk = $conn->query("SELECT id FROM events WHERE status = 'Active' LIMIT 1");
    if($r = $chk->fetch_assoc()) {
        $event_id = $r['id'];
    } else {
        die("No Active Event Selected. Please go to Settings > Print Report.");
    }
} else {
    $event_id = (int)$_GET['event_id'];
}

$data = ReportModel::generate($event_id);
if(!$data) die("Event data not found.");

$evt = $data['event'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Report - <?= htmlspecialchars($evt['title']) ?></title>
    <style>
        body { font-family: 'Times New Roman', serif; color: #000; background: #fff; margin: 0; padding: 20px; font-size: 10pt; }
        
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .page-break { page-break-before: always; }
        .no-break { page-break-inside: avoid; }
        
        /* Header Layout */
        .report-header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
        .logo-img { height: 80px; width: auto; margin-bottom: 10px; } /* Adjust logo size here */
        .event-title { font-size: 20pt; margin: 0; font-weight: 900; }
        .event-meta { font-size: 11pt; margin-top: 5px; }
        .report-label { font-size: 9pt; font-style: italic; margin-top: 5px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #000; padding: 4px 6px; text-align: left; vertical-align: middle; }
        th { background-color: #f0f0f0; text-transform: uppercase; font-size: 8pt; text-align: center; font-weight: bold; }
        
        .rank-col { width: 40px; text-align: center; font-weight: bold; font-size: 11pt; }
        .score-col { width: 60px; text-align: center; font-weight: bold; }

        .section-title { font-size: 12pt; border-bottom: 1px solid #000; margin: 25px 0 10px 0; padding-bottom: 3px; font-weight: bold; }
        
        /* Highlighting Styles */
        .qualifier-row { background-color: #f0fdf4 !important; } 
        .winner-row { background-color: #fffbeb !important; } 
        
        .badge { 
            display: inline-block; 
            font-size: 7pt; 
            padding: 1px 4px; 
            border-radius: 3px; 
            margin-left: 5px; 
            vertical-align: middle;
            font-weight: bold;
            color: #fff;
            border: 1px solid #000;
        }
        .badge-winner { background: #000; }
        .badge-qualifier { background: #15803d; border-color: #15803d; }

        .watermark {
            position: fixed; top: 30%; left: 15%; 
            font-size: 80pt; color: rgba(0,0,0,0.05); 
            transform: rotate(-45deg); z-index: -1;
            pointer-events: none;
        }

        .signature-grid { display: flex; flex-wrap: wrap; gap: 30px; margin-top: 40px; justify-content: center; }
        .sig-block { width: 200px; text-align: center; margin-bottom: 30px; }
        .sig-line { border-bottom: 1px solid #000; height: 30px; margin-bottom: 5px; }
        .sig-role { font-size: 8pt; font-style: italic; }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            table { table-layout: fixed; width: 100%; }
            /* Force background colors to print */
            .qualifier-row { -webkit-print-color-adjust: exact; print-color-adjust: exact; background-color: #f0fdf4 !important; }
            .winner-row { -webkit-print-color-adjust: exact; print-color-adjust: exact; background-color: #fffbeb !important; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="background:#333; color:#fff; padding:10px; text-align:center; position:fixed; top:0; left:0; width:100%; z-index:1000;">
        <button onclick="window.print()" style="padding:8px 20px; font-weight:bold; cursor:pointer;">🖨️ PRINT OFFICIAL REPORT</button>
    </div>
    <div class="no-print" style="height:60px;"></div>

    <div class="report-header">
        <img src="assets/images/BPMS_logo.png" alt="Logo" class="logo-img" onerror="this.style.display='none'">
        
        <h1 class="event-title"><?= htmlspecialchars($evt['title']) ?></h1>
        <div class="event-meta">
            <?= date('F j, Y', strtotime($evt['event_date'])) ?> | <?= htmlspecialchars($evt['venue']) ?>
        </div>
        <div class="report-label">OFFICIAL TABULATION REPORT</div>
    </div>

    <div class="section-title">I. OFFICIALS</div>
    <table>
        <thead>
            <tr>
                <th width="50%">PANEL OF JUDGES</th>
                <th width="50%">ORGANIZING COMMITTEE</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td valign="top">
                    <?php foreach($data['judges'] as $j): ?>
                        <div style="margin-bottom:3px;">
                            <b><?= htmlspecialchars($j['name']) ?></b> 
                            <?= $j['is_chairman'] ? '(Chairman)' : '' ?>
                        </div>
                    <?php endforeach; ?>
                </td>
                <td valign="top">
                    <?php foreach($data['organizers'] as $o): ?>
                        <div style="margin-bottom:3px;">
                            <?= htmlspecialchars($o['name']) ?> <small>(<?= $o['role'] ?>)</small>
                        </div>
                    <?php endforeach; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">II. CONTESTANT MASTER LIST</div>
    <table>
        <thead>
            <tr>
                <th width="40">#</th>
                <th>NAME</th>
                <th>HOMETOWN</th>
                <th>AGE</th>
                <th>VITAL STATS</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($data['contestants'] as $c): ?>
            <tr>
                <td class="text-center"><?= $c['contestant_number'] ?></td>
                <td><b><?= htmlspecialchars($c['name']) ?></b></td>
                <td><?= htmlspecialchars($c['hometown'] ?? '') ?></td>
                <td class="text-center"><?= $c['age'] ?></td>
                <td class="text-center"><?= htmlspecialchars($c['vital_stats']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">III. OFFICIAL RESULTS (AWARDS)</div>
    <table>
        <thead>
            <tr>
                <th width="40%">AWARD TITLE</th>
                <th width="20%">CATEGORY</th>
                <th>WINNER</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($data['awards'] as $aw): ?>
            <tr>
                <td><b><?= htmlspecialchars($aw['title']) ?></b></td>
                <td class="text-center"><?= $aw['type'] ?></td>
                <td><?= htmlspecialchars($aw['winner']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php foreach($data['rounds'] as $round): 
        // --- LOGIC TO DETERMINE WINNERS/QUALIFIERS ---
        $qualify_count = isset($round['info']['qualify_count']) ? (int)$round['info']['qualify_count'] : 1;
        $round_type = $round['info']['type'] ?? 'Elimination'; 
    ?>
        <div class="page-break"></div>
        
        <?php if($round['info']['status'] !== 'Completed'): ?>
            <div class="watermark">DRAFT</div>
        <?php endif; ?>

        <div class="report-header">
            <h2 style="margin:0; font-size:16pt;"><?= strtoupper(htmlspecialchars($round['info']['title'])) ?></h2>
            <div class="report-label">
                STATUS: <?= strtoupper($round['info']['status']) ?> 
                | <?= ($round_type == 'Final') ? "WINNER DECLARED" : "TOP $qualify_count QUALIFY" ?>
            </div>
        </div>

        <div class="section-title">A. RANKING SUMMARY</div>
        <table>
            <thead>
                <tr>
                    <th class="rank-col">RK</th>
                    <th>CONTESTANT</th>
                    <?php foreach($data['judges'] as $j): ?>
                        <th class="text-center" style="width:60px;"><?= strtoupper(substr($j['name'], 0, 8)) ?>.</th>
                    <?php endforeach; ?>
                    <th class="score-col">AVG</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($round['leaderboard'])): ?>
                    <tr><td colspan="15" class="text-center">No scores recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach($round['leaderboard'] as $row): 
                        // --- CHECK QUALIFICATION ---
                        $rank = $row['rank'];
                        $is_qualified = ($rank <= $qualify_count);
                        
                        // Style Logic
                        $row_class = '';
                        $badge_html = '';

                        if ($is_qualified) {
                            if ($round_type == 'Final' && $rank == 1) {
                                // The Grand Winner
                                $row_class = 'winner-row';
                                $badge_html = '<span class="badge badge-winner">🏆 WINNER</span>';
                            } else {
                                // Just advancing to next round
                                $row_class = 'qualifier-row';
                                $badge_html = '<span class="badge badge-qualifier">✅ QUALIFIED</span>';
                            }
                        }
                    ?>
                    <tr class="no-break <?= $row_class ?>">
                        <td class="rank-col"><?= $row['rank'] ?></td>
                        <td>
                            <b><?= htmlspecialchars($row['contestant']['name']) ?></b>
                            <?= $badge_html ?>
                            <br><small>#<?= $row['contestant']['contestant_number'] ?> | <?= htmlspecialchars($row['contestant']['hometown'] ?? '') ?></small>
                        </td>
                        <?php foreach($data['judges'] as $j): 
                             $jid = $j['id'];
                             $scoreVal = isset($row['judge_scores'][$jid]) ? number_format($row['judge_scores'][$jid], 2) : '-';
                        ?> 
                            <td class="text-center"><?= $scoreVal ?></td>
                        <?php endforeach; ?>
                        <td class="score-col"><?= number_format($row['final_score'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="section-title">B. DETAILED SCORING MATRIX</div>
        <?php 
            $audit = $round['audit'];
            if($audit && !empty($audit['segments'])):
                foreach($audit['segments'] as $seg): 
                    $weight = isset($seg['weight_percent']) ? $seg['weight_percent'] : (isset($seg['weight_percentage']) ? $seg['weight_percentage'] : 0);
        ?>
            <div class="no-break" style="margin-bottom:20px;">
                <div class="bold uppercase" style="background:#eee; padding:5px; border:1px solid #000; border-bottom:none; font-size:9pt;">
                    SEGMENT: <?= htmlspecialchars($seg['title']) ?> (Weight: <?= $weight ?>%)
                </div>
                <table>
                    <thead>
                        <tr>
                            <th width="20%">CONTESTANT</th>
                            <?php foreach($audit['judges'] as $j): ?>
                                <th class="text-center"><?= $j['name'] ?></th>
                            <?php endforeach; ?>
                            <th width="60">WTD. AVG</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($round['leaderboard'] as $row): 
                            $uid = $row['contestant']['detail_id'];
                        ?>
                        <tr>
                            <td>#<?= $row['contestant']['contestant_number'] ?> <?= $row['contestant']['name'] ?></td>
                            <?php 
                                $total_weighted = 0;
                                foreach($audit['judges'] as $j): 
                                    $jid = $j['id'];
                                    $raw_sum = 0;
                                    $has_score = false;
                                    
                                    foreach($audit['criteria'] as $crit) {
                                        if($crit['segment_id'] == $seg['id']) {
                                            $val = $audit['scores'][$uid][$jid][$crit['id']] ?? 0;
                                            if(isset($audit['scores'][$uid][$jid][$crit['id']])) $has_score = true;
                                            $raw_sum += $val;
                                        }
                                    }
                                    
                                    $w_score = $raw_sum * ($weight / 100);
                                    if($has_score) $total_weighted += $w_score;
                            ?>
                                <td class="text-center" style="color:#555;">
                                    <?= $has_score ? number_format($raw_sum, 2) : '-' ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-center bold">
                                <?php 
                                    $j_count = count($audit['judges']);
                                    echo $j_count > 0 ? number_format($total_weighted / $j_count, 2) : '0.00';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; endif; ?>

    <?php endforeach; ?>

    <div class="page-break"></div>
    <div style="margin-top:100px; text-align:center;">
        <h3 style="text-transform:uppercase;">Certification of Results</h3>
        <p style="width:80%; margin:0 auto;">
            We hereby certify that the scores and results presented in this report are true, accurate, <br>
            and generated by the official Beauty Pageant Management System.
        </p>
    </div>

    <div class="signature-grid">
        <?php foreach($data['judges'] as $j): ?>
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="bold"><?= strtoupper($j['name']) ?></div>
            <div class="sig-role">Official Judge</div>
        </div>
        <?php endforeach; ?>
        
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="bold">OFFICIAL TABULATOR</div>
            <div class="sig-role">System Administrator</div>
        </div>

        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="bold">EVENT MANAGER</div>
            <div class="sig-role">Organizer</div>
        </div>
    </div>

</body>
</html>