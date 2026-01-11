<?php
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
        die("No Event Selected. Please go to Settings > Print Report.");
    }
} else {
    $event_id = (int)$_GET['event_id'];
}

// 2. Fetch Data
$data = ReportModel::generate($event_id);
if(!$data) die("Event not found.");

$evt = $data['event'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Report - <?= htmlspecialchars($evt['name']) ?></title>
    <style>
        body { font-family: 'Times New Roman', serif; color: #000; background: #fff; margin: 0; padding: 20px; }
        
        /* Utility */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .page-break { page-break-before: always; }
        .no-break { page-break-inside: avoid; }
        
        /* Headers */
        .report-header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .event-title { font-size: 24pt; margin: 0; }
        .event-meta { font-size: 12pt; margin-top: 5px; }
        .report-label { font-size: 10pt; font-style: italic; margin-top: 10px; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 10pt; }
        th, td { border: 1px solid #000; padding: 5px 8px; text-align: left; }
        th { background-color: #f0f0f0; text-transform: uppercase; font-size: 9pt; }
        .rank-col { width: 50px; text-align: center; font-weight: bold; }
        .score-col { width: 80px; text-align: center; }

        /* Sections */
        .section-title { font-size: 14pt; border-bottom: 1px solid #000; margin: 30px 0 15px 0; padding-bottom: 5px; }
        
        /* Watermark for Drafts */
        .watermark {
            position: fixed; top: 30%; left: 10%; 
            font-size: 100pt; color: rgba(0,0,0,0.05); 
            transform: rotate(-45deg); z-index: -1;
            pointer-events: none;
        }

        /* Signature Area */
        .signature-grid { display: flex; flex-wrap: wrap; gap: 40px; margin-top: 50px; justify-content: center; }
        .sig-block { width: 200px; text-align: center; margin-bottom: 40px; }
        .sig-line { border-bottom: 1px solid #000; height: 30px; margin-bottom: 5px; }
        .sig-role { font-size: 9pt; font-style: italic; }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            /* Fix for squashed tables: Allow auto layout */
            table { table-layout: auto !important; width: 100%; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="background:#333; color:#fff; padding:10px; text-align:center; position:fixed; top:0; left:0; width:100%; z-index:1000;">
        <button onclick="window.print()" style="padding:10px 20px; font-weight:bold; cursor:pointer;">🖨️ PRINT OFFICIAL REPORT</button>
        <div style="font-size:11px; margin-top:5px;">Recommended: A4 Paper, Landscape Mode (for Matrix), Portrait (for Summary)</div>
    </div>
    <div class="no-print" style="height:60px;"></div> <div class="report-header text-center">
        <h1 class="event-title"><?= htmlspecialchars($evt['name']) ?></h1>
        <div class="event-meta">
            <?= date('F j, Y', strtotime($evt['event_date'])) ?> | <?= htmlspecialchars($evt['venue']) ?>
        </div>
        <div class="report-label">OFFICIAL TABULATION REPORT • GENERATED: <?= date('Y-m-d H:i:s') ?></div>
    </div>

    <div class="section-title">I. OFFICIALS</div>
    <table>
        <tr>
            <th colspan="2">PANEL OF JUDGES</th>
            <th colspan="2">ORGANIZING COMMITTEE</th>
        </tr>
        <tr>
            <td valign="top" width="50%">
                <?php foreach($data['judges'] as $j): ?>
                    <div style="margin-bottom:4px;">
                        <b><?= htmlspecialchars($j['name']) ?></b> 
                        <?= $j['is_chairman'] ? '(Chairman)' : '' ?>
                    </div>
                <?php endforeach; ?>
            </td>
            <td valign="top" width="50%">
                <?php foreach($data['organizers'] as $o): ?>
                    <div style="margin-bottom:4px;">
                        <?= htmlspecialchars($o['name']) ?> <small>(<?= $o['role'] ?>)</small>
                    </div>
                <?php endforeach; ?>
            </td>
        </tr>
    </table>

    <div class="section-title">II. CONTESTANT MASTER LIST</div>
    <table>
        <thead>
            <tr>
                <th width="50">#</th>
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
                <td><?= htmlspecialchars($c['hometown']) ?></td>
                <td><?= $c['age'] ?></td>
                <td><?= htmlspecialchars($c['vital_stats']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-title">III. CONSOLIDATED RESULTS (AWARDS)</div>
    <table>
        <thead>
            <tr>
                <th>AWARD TITLE</th>
                <th>CATEGORY</th>
                <th>WINNER</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($data['awards'] as $aw): ?>
            <tr>
                <td><b><?= htmlspecialchars($aw['title']) ?></b></td>
                <td><?= $aw['type'] ?></td>
                <td><?= htmlspecialchars($aw['winner']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php foreach($data['rounds'] as $round): ?>
        <div class="page-break"></div>
        
        <?php if($round['info']['status'] !== 'Completed'): ?>
            <div class="watermark">DRAFT</div>
        <?php endif; ?>

        <div class="report-header text-center">
            <h2 style="margin:0;"><?= strtoupper(htmlspecialchars($round['info']['title'])) ?></h2>
            <div class="report-label">STATUS: <?= strtoupper($round['info']['status']) ?></div>
        </div>

        <div class="section-title">A. FINAL RANKING (LEADERBOARD)</div>
        <table>
            <thead>
                <tr>
                    <th class="rank-col">RANK</th>
                    <th>CONTESTANT</th>
                    <?php foreach($data['judges'] as $j): ?>
                        <th class="text-center" style="font-size:8pt;"><?= strtoupper($j['name']) ?></th>
                    <?php endforeach; ?>
                    <th class="score-col">FINAL AVG</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($round['leaderboard'])): ?>
                    <tr><td colspan="10" class="text-center">No scores recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach($round['leaderboard'] as $row): ?>
                    <tr class="no-break">
                        <td class="rank-col" style="font-size:12pt;"><?= $row['rank'] ?></td>
                        <td>
                            <b><?= htmlspecialchars($row['contestant']['name']) ?></b><br>
                            <small>#<?= $row['contestant']['contestant_number'] ?></small>
                        </td>
                        <?php foreach($data['judges'] as $j): 
                             // MATCHING LOGIC: Map Judge ID to Score Array
                             $jid = $j['id'];
                             $scoreVal = isset($row['judge_scores'][$jid]) ? number_format($row['judge_scores'][$jid], 2) : '-';
                        ?> 
                            <td class="text-center"><?= $scoreVal ?></td>
                        <?php endforeach; ?>
                        <td class="score-col bold"><?= number_format($row['final_score'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="section-title">B. DETAILED AUDIT MATRIX</div>
        <?php 
            $audit = $round['audit'];
            if($audit):
                foreach($audit['segments'] as $seg): 
        ?>
            <div class="no-break" style="margin-bottom:20px;">
                <div class="bold uppercase" style="border-bottom:1px solid #000; margin-bottom:5px;">
                    SEGMENT: <?= htmlspecialchars($seg['title']) ?> (<?= $seg['weight_percentage'] ?>%)
                </div>
                <table>
                    <thead>
                        <tr>
                            <th width="150">CONTESTANT</th>
                            <?php foreach($audit['judges'] as $j): ?>
                                <th class="text-center"><?= $j['name'] ?></th>
                            <?php endforeach; ?>
                            <th width="60">WTD.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($round['leaderboard'] as $row): 
                            $uid = $row['contestant']['user_id'];
                        ?>
                        <tr>
                            <td><?= $row['contestant']['name'] ?></td>
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
                                    $w_score = $raw_sum * ($seg['weight_percentage'] / 100);
                                    if($has_score) $total_weighted += $w_score;
                            ?>
                                <td class="text-center">
                                    <?= $has_score ? number_format($raw_sum, 2) : '-' ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-center bold">
                                <?= number_format($total_weighted / count($audit['judges']), 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; endif; ?>

    <?php endforeach; ?>

    <div class="page-break"></div>
    <div class="section-title text-center" style="border:none; margin-top:100px;">CERTIFICATION OF RESULTS</div>
    <p class="text-center">
        We hereby certify that the scores and results presented in this report are true, accurate, <br>
        and generated by the official Beauty Pageant Management System.
    </p>

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