<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once '../assets/db.php';
require_once '../assets/functions.php';

$my_id = $_SESSION['user_id'];
$heute = date('Y-m-d');
$msg = "";

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $check = mysqli_query($conn, "SELECT * FROM absences WHERE id = $id AND user_id = $my_id");
    if(mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "DELETE FROM absences WHERE id = $id");
        $_SESSION['msg'] = "Abmeldung gelöscht!";
    }
    header("Location: meine_abmeldungen"); exit;
}

function logActionLocal($conn, $target_id, $action_type, $details) {
    $performer_id = $_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "INSERT INTO action_logs (performer_id, target_id, action_type, details) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iiss", $performer_id, $target_id, $action_type, $details);
    mysqli_stmt_execute($stmt);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id = (int)$_POST['id'];
        $start = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end = mysqli_real_escape_string($conn, $_POST['end_date']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        
        mysqli_query($conn, "UPDATE absences SET start_date='$start', end_date='$end', reason='$reason' WHERE id=$id AND user_id=$my_id");
        $_SESSION['msg'] = "Erfolgreich aktualisiert!";
        header("Location: meine_abmeldungen"); exit;
    }

    if (isset($_POST['add_absence'])) {
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = trim($_POST['reason']);
        $authorid = trim($my_id);
        $stmt = mysqli_prepare($conn, "INSERT INTO absences (user_id, start_date, end_date, reason, created_by) VALUES (?, ?, ?, ?, ?)");
        logActionLocal($conn, $my_id, 'ABSENCE_ADD', 'Zeitraum: ' . $_POST['start_date'] . ' bis ' . $_POST['end_date']);
        mysqli_stmt_bind_param($stmt, "isssi", $my_id, $start_date, $end_date, $reason, $authorid);
        mysqli_stmt_execute($stmt);
        header("Location: $self?id=$my_id&msg=abmeldung_hinzugefuegt");
        exit;
    }
}

if (isset($_SESSION['msg'])) { $msg = $_SESSION['msg']; $is_success = true; unset($_SESSION['msg']); }

$all_my_absences = mysqli_query($conn, "SELECT * FROM absences WHERE user_id = $my_id ORDER BY start_date ASC");
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Meine Abmeldungen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles/meine_abmeldungen.css">
    <style>
        .content-container { padding: 0 2rem; }
        .btn-action { height: 32px; padding: 2px 10px; font-size: 0.8rem; }
    </style>
</head>
<body>

<?php include '../assets/navbar.php'; ?>

<div class="mt-4 abmeldungen-container">
    <?php if($msg): ?>
        <div id="statusBanner" class="alert <?php echo (isset($is_success)) ? 'alert-success' : 'alert-danger'; ?>"><?php echo $msg; ?></div>
    <?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0">Meine Abmeldungen</h2>
    <button class="btn btn-sm btn-primary btn-action" onclick="openModal('add')">+ Neue Abmeldung</button>
</div>
        <div class="card bg-dark border-secondary">
            <div class="card-body p-0">
                <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th style="padding-left: 20px;">Von</th>
                        <th>Bis</th>
                        <th>Status</th>
                        <th>Grund</th>
                        <th class="text-end" style="padding-right: 20px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($abs = mysqli_fetch_assoc($all_my_absences)): 
                        $is_expired = ($abs['end_date'] < $heute);
                        $status = $is_expired ? '<span class="badge bg-secondary">Abgelaufen</span>' : (($abs['start_date'] <= $heute) ? '<span class="badge bg-success">Aktiv</span>' : '<span class="badge bg-primary">Geplant</span>');
                    ?>
                    <tr>
                        <td style="padding-left: 20px;"><?php echo date('d.m.Y', strtotime($abs['start_date'])); ?></td>
                        <td><?php echo date('d.m.Y', strtotime($abs['end_date'])); ?></td>
                        <td><?php echo $status; ?></td>
                        <td><?php echo htmlspecialchars($abs['reason']); ?></td>
                        <td class="text-end" style="padding-right: 20px;">
                            <div class="d-inline-flex gap-2">
                                <?php if (!$is_expired): ?>
                                    <button class="btn btn-sm btn-outline-primary btn-action" onclick="openModal('edit', <?php echo $abs['id']; ?>, '<?php echo addslashes($abs['reason']); ?>', '<?php echo $abs['start_date']; ?>', '<?php echo $abs['end_date']; ?>')">Bearbeiten</button>
                                    <button class="btn btn-sm btn-outline-danger btn-action" onclick="openModal('delete', <?php echo $abs['id']; ?>)">Löschen</button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary btn-action" disabled>Gesperrt</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content bg-dark text-white border border-secondary"><div class="modal-header border-secondary"><h5 class="modal-title" id="modalTitle"></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body" id="modalBody"></div></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal(mode, id, reason = '', start = '', end = '') {
    const title = document.getElementById('modalTitle');
    const body = document.getElementById('modalBody');
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    if (mode === 'add') {
        title.innerText = 'Neue Abmeldung';
        body.innerHTML = '<form action="meine_abmeldungen" method="POST">' +
            '<label>Starttag</label><input type="date" name="start_date" class="form-control bg-dark text-white" required>' +
            '<label>Endtag</label><input type="date" name="end_date" class="form-control bg-dark text-white" required>' +
            '<label>Grund</label><textarea name="reason" class="form-control bg-dark text-white" rows="3" required></textarea>' +
            '<div class="mt-3 text-end">' +
            '<button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Abbrechen</button>' +
            '<button type="submit" name="add_absence" class="btn btn-primary">Speichern</button>' +
            '</div></form>';                                 
    } else if (mode === 'edit') {
        title.innerText = 'Bearbeiten';
        body.innerHTML = `<form action="meine_abmeldungen" method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="${id}"><label>Start:</label><input type="date" class="form-control bg-dark text-white mb-2" name="start_date" value="${start}" required><label>Ende:</label><input type="date" class="form-control bg-dark text-white mb-2" name="end_date" value="${end}" required><label>Grund:</label><textarea class="form-control bg-dark text-white mb-3" name="reason" rows="3" required>${reason}</textarea><button type="submit" class="btn btn-primary w-100">Speichern</button></form>`;
    } else {
        title.innerText = 'Löschen?';
        body.innerHTML = `<p>Wirklich entfernen?</p><a href="?action=delete&id=${id}" class="btn btn-danger w-100">Löschen</a>`;
    }
    modal.show();
}
</script>
</body>
</html>