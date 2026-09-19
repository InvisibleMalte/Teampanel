<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once '../assets/db.php';
require_once '../assets/functions.php';

function logActionLocal($conn, $target_id, $action_type, $details) {
    $performer_id = $_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "INSERT INTO action_logs (performer_id, target_id, action_type, details) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iiss", $performer_id, $target_id, $action_type, $details);
    mysqli_stmt_execute($stmt);
}

$my_id = (int)$_SESSION['user_id'];

$toastMessages = [
    'abmeldung_gespeichert' => 'Abmeldung gespeichert.',
    'abmeldung_entfernt'    => 'Abmeldung zurückgezogen.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_meeting_absence'])) {
    $meeting_id = (int)$_POST['meeting_id'];
    $stmt = mysqli_prepare($conn, "DELETE FROM meeting_absences WHERE meeting_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $meeting_id, $my_id);
    mysqli_stmt_execute($stmt);
    logActionLocal($conn, $my_id, 'MEETING_ABSENCE_REMOVE', 'Abmeldung für Teambesprechung ID ' . $meeting_id . ' zurückgezogen.');
    header("Location: team_meetings?msg=abmeldung_entfernt");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_meeting_absence'])) {
    $meeting_id = (int)$_POST['meeting_id'];
    $reason = trim($_POST['reason']);
    if ($reason !== '') {
        $stmt = mysqli_prepare($conn, "INSERT INTO meeting_absences (meeting_id, user_id, reason) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason)");
        mysqli_stmt_bind_param($stmt, "iis", $meeting_id, $my_id, $reason);
        mysqli_stmt_execute($stmt);
        logActionLocal($conn, $my_id, 'MEETING_ABSENCE_ADD', 'Abmeldung für Teambesprechung ID ' . $meeting_id . ' eingetragen.');
    }
    header("Location: team_meetings?msg=abmeldung_gespeichert");
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT tm.*, pw.username AS protocol_writer_name FROM team_meetings tm LEFT JOIN users pw ON tm.protocol_writer_id = pw.id ORDER BY tm.meeting_date DESC");
mysqli_stmt_execute($stmt);
$meetings = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$stmt = mysqli_prepare($conn, "SELECT meeting_id, reason FROM meeting_absences WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $my_id);
mysqli_stmt_execute($stmt);
$myAbsenceRows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
$myAbsences = [];
foreach ($myAbsenceRows as $r) { $myAbsences[(int)$r['meeting_id']] = $r['reason']; }

$stmt = mysqli_prepare($conn, "SELECT meeting_id, status FROM meeting_attendance WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $my_id);
mysqli_stmt_execute($stmt);
$myAttendanceRows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
$myAttendance = [];
foreach ($myAttendanceRows as $r) { $myAttendance[(int)$r['meeting_id']] = $r['status']; }

$toastText = null;
if (isset($_GET['msg']) && isset($toastMessages[$_GET['msg']])) {
    $toastText = $toastMessages[$_GET['msg']];
}

function participationBadge($meetingId, $isFuture, $myAbsences, $myAttendance) {
    if (isset($myAbsences[$meetingId])) {
        return '<span class="badge bg-warning text-dark">Abgemeldet</span>';
    }
    if ($isFuture) {
        return '<span class="badge bg-secondary">Keine Abmeldung</span>';
    }
    if (($myAttendance[$meetingId] ?? null) === 'anwesend') {
        return '<span class="badge bg-success">Anwesend</span>';
    }
    return '<span class="badge bg-danger">Unentschuldigt gefehlt</span>';
}
?>
<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Teambesprechungen</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #121212 !important; color: #fff; }
            .card-header { background-color: #333 !important; color: #fff !important; font-weight: bold; }
            .table { color: #ccc; table-layout: fixed; }
            .table td, .table th { vertical-align: middle; word-wrap: break-word; }
            label { color: #ffffff !important; font-weight: 500; }
            .actions-cell { text-align: right; padding-right: 10px; }
            .modal-content { background-color: #1e1e1e; }
        </style>
    </head>
    <body>
    <?php include '../assets/navbar.php'; ?>

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
        <div id="actionToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
            <div class="d-flex">
                <div class="toast-body" id="actionToastBody"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <h2 class="mb-4 text-white">Teambesprechungen</h2>

        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary">Übersicht</div>
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 14%;">Datum</th>
                        <th style="width: 12%;">Protokoll</th>
                        <th style="width: 15%;">Meine Teilnahme</th>
                        <th style="width: 16%;">Protokollant</th>
                        <th class="actions-cell">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meetings as $m):
                        $isFuture = strtotime($m['meeting_date']) > time();
                        $alreadyAbsent = array_key_exists((int)$m['id'], $myAbsences);
                    ?>
                    <tr>
                        <td><?php echo date('d.m.y H:i', strtotime($m['meeting_date'])); ?></td>
                        <td>
                            <?php if ($m['finalized_at']): ?>
                                <span class="badge bg-success">Fertig</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">In Bearbeitung</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo participationBadge((int)$m['id'], $isFuture, $myAbsences, $myAttendance); ?></td>
                        <td><?php echo htmlspecialchars($m['protocol_writer_name'] ?? '-'); ?></td>
                        <td class="actions-cell">
                            <a href="team_meeting_detail?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-primary">Öffnen</a>
                            <a href="team_meeting_print?id=<?php echo $m['id']; ?>" target="_blank" class="btn btn-sm btn-outline-light">PDF</a>
                            <?php if ((int)$m['protocol_writer_id'] === $my_id): ?>
                                <a href="team_meeting_detail?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-warning">Protokoll schreiben</a>
                            <?php endif; ?>
                            <?php if ($isFuture): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#absenceModal<?php echo $m['id']; ?>"><?php echo $alreadyAbsent ? 'Abmeldung ändern' : 'Abmelden'; ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach ($meetings as $m):
        if (strtotime($m['meeting_date']) <= time()) continue;
        $currentReason = $myAbsences[(int)$m['id']] ?? '';
        $alreadyAbsent = array_key_exists((int)$m['id'], $myAbsences);
    ?>
    <div class="modal fade" id="absenceModal<?php echo $m['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <form method="POST">
                    <input type="hidden" name="meeting_id" value="<?php echo $m['id']; ?>">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Von Teambesprechung abmelden</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p><?php echo date('d.m.y H:i', strtotime($m['meeting_date'])); ?> &ndash; Teambesprechung</p>
                        <div class="mb-3">
                            <label>Grund</label>
                            <textarea name="reason" class="form-control bg-dark text-white" rows="3" required><?php echo htmlspecialchars($currentReason); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <?php if ($alreadyAbsent): ?>
                            <button type="submit" name="remove_meeting_absence" formnovalidate class="btn btn-outline-danger">Abmeldung zurückziehen</button>
                        <?php endif; ?>
                        <button type="submit" name="add_meeting_absence" class="btn btn-primary">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($toastText): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('actionToastBody').textContent = <?php echo json_encode($toastText); ?>;
            bootstrap.Toast.getOrCreateInstance(document.getElementById('actionToast')).show();
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('msg');
                window.history.replaceState({}, '', url);
            }
        });
        <?php endif; ?>
    </script>
    </body>
</html>