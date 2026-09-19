<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once '../assets/db.php';
require_once '../assets/functions.php';
require_once '../assets/markdown.php';

if (!isset($_GET['id'])) { die("Keine Besprechungs-ID angegeben."); }
$meeting_id = (int)$_GET['id'];

$stmt = mysqli_prepare($conn, "SELECT tm.*, h.username AS host_name, pw.username AS protocol_writer_name, lu.username AS last_updated_by_name FROM team_meetings tm LEFT JOIN users h ON tm.host_id = h.id LEFT JOIN users pw ON tm.protocol_writer_id = pw.id LEFT JOIN users lu ON tm.last_updated_by = lu.id WHERE tm.id = ?");
mysqli_stmt_bind_param($stmt, "i", $meeting_id);
mysqli_stmt_execute($stmt);
$meeting = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$meeting) { die("Teambesprechung nicht gefunden."); }

$stmt = mysqli_prepare($conn, "SELECT id, username FROM users WHERE role_id != -1 ORDER BY username ASC");
mysqli_stmt_execute($stmt);
$activeUsers = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$stmt = mysqli_prepare($conn, "SELECT user_id, reason FROM meeting_absences WHERE meeting_id = ?");
mysqli_stmt_bind_param($stmt, "i", $meeting_id);
mysqli_stmt_execute($stmt);
$absenceRows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
$absences = [];
foreach ($absenceRows as $r) { $absences[(int)$r['user_id']] = $r['reason']; }

$stmt = mysqli_prepare($conn, "SELECT user_id, status FROM meeting_attendance WHERE meeting_id = ?");
mysqli_stmt_bind_param($stmt, "i", $meeting_id);
mysqli_stmt_execute($stmt);
$attendanceRows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
$attendanceMap = [];
foreach ($attendanceRows as $r) { $attendanceMap[(int)$r['user_id']] = $r['status']; }

$present = [];
$absentRegistered = [];
$missing = [];
foreach ($activeUsers as $u2) {
    $uid = (int)$u2['id'];
    if (isset($absences[$uid])) {
        $absentRegistered[] = $u2['username'] . ' (' . $absences[$uid] . ')';
    } elseif (isset($attendanceMap[$uid]) && $attendanceMap[$uid] === 'anwesend') {
        $present[] = $u2['username'];
    } else {
        $missing[] = $u2['username'];
    }
}

$renderedProtocol = renderMarkdown($meeting['protocol_content']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Protokoll <?php echo htmlspecialchars(date('d.m.Y', strtotime($meeting['meeting_date']))); ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #000; padding: 24px; }
        table { border-collapse: collapse; width: 100%; margin: 12px 0; }
        table, th, td { border: 1px solid #333; padding: 6px; }
        .no-print { margin-bottom: 20px; }
        h1 { font-size: 20px; }
        .summary-text { white-space: pre-wrap; }
        .footer-note { margin-top: 40px; font-size: 10px; color: #666; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Als PDF speichern / Drucken</button>
    </div>

    <h1>Teambesprechung vom <?php echo date('d.m.Y H:i', strtotime($meeting['meeting_date'])); ?></h1>
    <p>
        <strong>Status:</strong>
        <?php echo $meeting['finalized_at'] ? 'Fertiggestellt am ' . date('d.m.Y H:i', strtotime($meeting['finalized_at'])) : 'In Bearbeitung'; ?>
    </p>
    <p>
        <strong>Gesprächsleiter:</strong> <?php echo htmlspecialchars($meeting['host_name'] ?? '-'); ?><br>
        <strong>Protokollant:</strong> <?php echo htmlspecialchars($meeting['protocol_writer_name'] ?? '-'); ?><br>
        <strong>Ende:</strong> <?php echo $meeting['end_time'] ? date('H:i', strtotime($meeting['end_time'])) . ' Uhr' : '-'; ?>
    </p>
    <p><strong>Zusammenfassung:</strong></p>
    <p class="summary-text"><?php echo !empty($meeting['summary']) ? htmlspecialchars($meeting['summary']) : '-'; ?></p>
    <p><strong>Anwesend:</strong> <?php echo htmlspecialchars(implode(', ', $present) ?: '-'); ?></p>
    <p><strong>Abgemeldet:</strong> <?php echo htmlspecialchars(implode(', ', $absentRegistered) ?: '-'); ?></p>
    <p><strong>Fehlend:</strong> <?php echo htmlspecialchars(implode(', ', $missing) ?: '-'); ?></p>
    <hr>
    <?php echo $renderedProtocol; ?>

    <p class="footer-note">
        Zuletzt geändert am <?php echo $meeting['updated_at'] ? date('d.m.Y H:i', strtotime($meeting['updated_at'])) : '-'; ?>
        <?php if (!empty($meeting['last_updated_by_name'])): ?>
            von <?php echo htmlspecialchars($meeting['last_updated_by_name']); ?>
        <?php endif; ?>
    </p>
</body>
</html>