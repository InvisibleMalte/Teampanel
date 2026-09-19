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
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $my_id);
mysqli_stmt_execute($stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!($me['role_id'] >= 2 || $me['is_admin'] == 1)) { die("Keine Berechtigung."); }

$toastMessages = [
    'erstellt'     => 'Teambesprechung erstellt.',
    'aktualisiert' => 'Teambesprechung aktualisiert.',
    'geloescht'    => 'Teambesprechung gelöscht.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_meeting'])) {
        $meeting_date = $_POST['meeting_date'];
        $host_id = (int)$_POST['host_id'];
        $protocol_writer_id = !empty($_POST['protocol_writer_id']) ? (int)$_POST['protocol_writer_id'] : null;
        $stmt = mysqli_prepare($conn, "INSERT INTO team_meetings (meeting_date, host_id, protocol_writer_id, last_updated_by) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "siii", $meeting_date, $host_id, $protocol_writer_id, $my_id);
        mysqli_stmt_execute($stmt);
        $newId = mysqli_insert_id($conn);
        logActionLocal($conn, $host_id, 'MEETING_CREATE', 'Teambesprechung ID ' . $newId . ' erstellt.');
        header("Location: leitung_meetings?msg=erstellt");
        exit;
    }

    if (isset($_POST['edit_meeting'])) {
        $mid = (int)$_POST['meeting_id'];
        $meeting_date = $_POST['meeting_date'];
        $host_id = (int)$_POST['host_id'];
        $protocol_writer_id = !empty($_POST['protocol_writer_id']) ? (int)$_POST['protocol_writer_id'] : null;
        $stmt = mysqli_prepare($conn, "UPDATE team_meetings SET meeting_date=?, host_id=?, protocol_writer_id=?, last_updated_by=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "siiii", $meeting_date, $host_id, $protocol_writer_id, $my_id, $mid);
        mysqli_stmt_execute($stmt);
        logActionLocal($conn, $host_id, 'MEETING_EDIT', 'Teambesprechung ID ' . $mid . ' bearbeitet.');
        header("Location: leitung_meetings?msg=aktualisiert");
        exit;
    }

    if (isset($_POST['delete_meeting'])) {
        $mid = (int)$_POST['meeting_id'];
        $stmt = mysqli_prepare($conn, "SELECT meeting_date, host_id FROM team_meetings WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $mid);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($row && strtotime($row['meeting_date']) > time()) {
            $stmt2 = mysqli_prepare($conn, "DELETE FROM team_meetings WHERE id = ?");
            mysqli_stmt_bind_param($stmt2, "i", $mid);
            mysqli_stmt_execute($stmt2);
            logActionLocal($conn, $row['host_id'], 'MEETING_DELETE', 'Teambesprechung ID ' . $mid . ' gelöscht.');
        }
        header("Location: leitung_meetings?msg=geloescht");
        exit;
    }
}

$stmt = mysqli_prepare($conn, "SELECT tm.*, h.username AS host_name, pw.username AS protocol_writer_name FROM team_meetings tm LEFT JOIN users h ON tm.host_id = h.id LEFT JOIN users pw ON tm.protocol_writer_id = pw.id ORDER BY tm.meeting_date DESC");
mysqli_stmt_execute($stmt);
$meetings = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$stmt = mysqli_prepare($conn, "SELECT meeting_id, COUNT(*) AS cnt FROM meeting_absences GROUP BY meeting_id");
mysqli_stmt_execute($stmt);
$absenceCountRows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
$absenceCounts = [];
foreach ($absenceCountRows as $r) { $absenceCounts[(int)$r['meeting_id']] = (int)$r['cnt']; }

$stmt = mysqli_prepare($conn, "SELECT id, username FROM users WHERE role_id != -1 ORDER BY username ASC");
mysqli_stmt_execute($stmt);
$activeUsers = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$toastText = null;
if (isset($_GET['msg']) && isset($toastMessages[$_GET['msg']])) {
    $toastText = $toastMessages[$_GET['msg']];
}
?>
<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Teambesprechungen verwalten</title>
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
        <h2 class="mb-4 text-white">Teambesprechungen verwalten</h2>

        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                <span>Übersicht</span>
                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addMeetingModal">+ Neue Teambesprechung</button>
            </div>
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 13%;">Datum</th>
                        <th style="width: 11%;">Status</th>
                        <th style="width: 11%;">Abmeldungen</th>
                        <th style="width: 15%;">Gesprächsleiter</th>
                        <th style="width: 15%;">Protokollant</th>
                        <th class="actions-cell">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meetings as $m):
                        $isFuture = strtotime($m['meeting_date']) > time();
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
                        <td><?php echo $absenceCounts[(int)$m['id']] ?? 0; ?></td>
                        <td><?php echo htmlspecialchars($m['host_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($m['protocol_writer_name'] ?? '-'); ?></td>
                        <td class="actions-cell">
                            <a href="team_meeting_detail?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-light">Öffnen</a>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editMeeting<?php echo $m['id']; ?>">Bearbeiten</button>
                            <?php if ($isFuture): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteMeeting<?php echo $m['id']; ?>">Löschen</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="addMeetingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <form method="POST">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Teambesprechung erstellen</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Datum & Uhrzeit</label>
                            <input type="datetime-local" name="meeting_date" class="form-control bg-dark text-white" required>
                        </div>
                        <div class="mb-3">
                            <label>Gesprächsleiter</label>
                            <select name="host_id" class="form-control bg-dark text-white" required>
                                <?php foreach ($activeUsers as $u2): ?>
                                    <option value="<?php echo $u2['id']; ?>"><?php echo htmlspecialchars($u2['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Protokollant</label>
                            <select name="protocol_writer_id" class="form-control bg-dark text-white">
                                <option value="">- Noch nicht festgelegt -</option>
                                <?php foreach ($activeUsers as $u2): ?>
                                    <option value="<?php echo $u2['id']; ?>"><?php echo htmlspecialchars($u2['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="add_meeting" class="btn btn-primary">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php foreach ($meetings as $m): ?>
    <div class="modal fade" id="editMeeting<?php echo $m['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <form method="POST">
                    <input type="hidden" name="meeting_id" value="<?php echo $m['id']; ?>">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Teambesprechung bearbeiten</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Datum & Uhrzeit</label>
                            <input type="datetime-local" name="meeting_date" class="form-control bg-dark text-white" value="<?php echo date('Y-m-d\TH:i', strtotime($m['meeting_date'])); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Gesprächsleiter</label>
                            <select name="host_id" class="form-control bg-dark text-white" required>
                                <?php foreach ($activeUsers as $u2): ?>
                                    <option value="<?php echo $u2['id']; ?>" <?php if((int)$m['host_id']===(int)$u2['id']) echo 'selected'; ?>><?php echo htmlspecialchars($u2['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Protokollant</label>
                            <select name="protocol_writer_id" class="form-control bg-dark text-white">
                                <option value="">- Noch nicht festgelegt -</option>
                                <?php foreach ($activeUsers as $u2): ?>
                                    <option value="<?php echo $u2['id']; ?>" <?php if((int)$m['protocol_writer_id']===(int)$u2['id']) echo 'selected'; ?>><?php echo htmlspecialchars($u2['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="edit_meeting" class="btn btn-primary">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteMeeting<?php echo $m['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <form method="POST">
                    <input type="hidden" name="meeting_id" value="<?php echo $m['id']; ?>">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Teambesprechung löschen</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Soll die Teambesprechung vom <?php echo date('d.m.y H:i', strtotime($m['meeting_date'])); ?> wirklich gelöscht werden?</p>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="delete_meeting" class="btn btn-danger">Löschen</button>
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