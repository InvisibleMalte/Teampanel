<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once 'assets/db.php';
require_once './assets/functions.php';

$my_id = $_SESSION['user_id'];
$heute = date('Y-m-d');
$msg = "";

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $abs_id = (int)$_GET['id'];
    $check = mysqli_query($conn, "SELECT * FROM absences WHERE id = $abs_id AND user_id = $my_id");
    if (mysqli_num_rows($check) > 0) {
        $old_data = mysqli_fetch_assoc($check);
        mysqli_query($conn, "DELETE FROM absences WHERE id = $abs_id");
        logAction($conn, $my_id, $my_id, "Abmeldung Gelöscht (Eigen)", "Gelöschter Zeitraum: {$old_data['start_date']} bis {$old_data['end_date']}. Grund: {$old_data['reason']}");
        $_SESSION['msg'] = "Abmeldung erfolgreich gelöscht!";
    }
    header("Location: /"); exit;
}

function logActionLocal($conn, $target_id, $action_type, $details) {
    $performer_id = $_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "INSERT INTO action_logs (performer_id, target_id, action_type, details) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iiss", $performer_id, $target_id, $action_type, $details);
    mysqli_stmt_execute($stmt);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $abs_id = (int)$_POST['id'];
        $start = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end = mysqli_real_escape_string($conn, $_POST['end_date']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        
        $check = mysqli_query($conn, "SELECT * FROM absences WHERE id = $abs_id AND user_id = $my_id");
        if (mysqli_num_rows($check) > 0) {
            $old_data = mysqli_fetch_assoc($check);
            $limit = date('Y-m-d', strtotime('-3 days'));
            
            if ($start !== $old_data['start_date'] && $start < $limit) {
                $msg = "Fehler: Startdatum darf nicht weiter als 3 Tage in der Vergangenheit liegen!";
            } else {
                mysqli_query($conn, "UPDATE absences SET start_date = '$start', end_date = '$end', reason = '$reason' WHERE id = $abs_id");
                logAction($conn, $my_id, $my_id, "Abmeldung Bearbeitet", "Neu: $start bis $end");
                $_SESSION['msg'] = "Erfolgreich aktualisiert!";
                header("Location: /"); exit;
            }
        }
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

$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT users.*, roles.role_name FROM users JOIN roles ON users.role_id = roles.id WHERE users.id = $my_id"));
$pun_res = mysqli_query($conn, "SELECT * FROM punishments WHERE user_id = $my_id");
$warns_cnt = 0; $strikes_cnt = 0;
while($p = mysqli_fetch_assoc($pun_res)) {
    if ($p['type'] === 'Verwarnung')  {
        $diff = (new DateTime($p['created_at']))->diff(new DateTime());
        if (!($diff->y > 0 || $diff->m >= 3)) $warns_cnt++;
    }
    if ($p['type'] === 'Strike') {
        $diff = (new DateTime($p['created_at']))->diff(new DateTime());
        if (!($diff->y > 0 || $diff->m >= 6)) $strikes_cnt++;
    }
}
$is_absent = (mysqli_num_rows(mysqli_query($conn, "SELECT id FROM absences WHERE user_id = $my_id AND CURDATE() >= start_date AND CURDATE() <= end_date")) > 0);
$total_absent_days = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(DATEDIFF(end_date, start_date) + 1) as total FROM absences WHERE user_id = $my_id"))['total'] ?? 0;
$my_absences = mysqli_query($conn, "SELECT * FROM absences WHERE user_id = $my_id AND end_date >= CURDATE() ORDER BY start_date ASC");
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Team Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles/index.css">
    <style>.btn-action { height: 35px; padding: 0 12px; }</style>
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/navbar.php'; ?>

<div class="container-fluid mt-4 px-4">
    <?php if($msg): ?><div id="statusBanner" class="alert <?php echo (isset($is_success)) ? 'alert-success' : 'alert-danger'; ?>"><?php echo $msg; ?></div><?php endif; ?>
    
    <h2 class="mb-4">Willkommen, <span style="color: #FF5555;"><?php echo htmlspecialchars($me['username']); ?></span>!</h2>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card h-100 p-3">
                <div class="card-header">Deine Statistiken</div>
                <div class="card-body">
                    <p><strong>Rang:</strong> <?php echo htmlspecialchars($me['internal_title']); ?> (<?php echo htmlspecialchars($me['role_name']); ?>)</p>
                    <p><strong>Im Team seit:</strong> <?php echo getTeamDuration($me['joined_at']); ?></p>
                    <p><strong>Status:</strong> <?php echo ($is_absent ? '<span class="text-warning">Derzeit Abgemeldet</span>' : '<span class="text-success">Derzeit Nicht Abgemeldet</span>'); ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100 p-3">
                <div class="card-header">Strafen & Abwesenheit</div>
                <div class="card-body">
                    <p>Aktive Strikes: <strong class="text-danger"><?php echo $strikes_cnt; ?></strong> | Aktive Verwarnungen: <strong class="text-danger"><?php echo $warns_cnt; ?></strong></p>
                    <p>Gesamte Abwesenheitstage: <strong><?php echo $total_absent_days; ?> Tage</strong></p>
                </div>
            </div>
        </div>

        <div class="col-12 mt-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Deine aktuellen Abmeldungen</span>
                    <button class="btn btn-sm btn-outline-primary btn-action" onclick="openModal('add')">+ Neue Abmeldung</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle">
                        <thead>
                            <tr><th>Von</th><th>Bis</th><th>Grund</th><th class="text-end">Aktionen</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($abs = mysqli_fetch_assoc($my_absences)): ?>
                            <tr>
                                <td><?php echo date('d.m.Y', strtotime($abs['start_date'])); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($abs['end_date'])); ?></td>
                                <td><?php echo htmlspecialchars($abs['reason']); ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary btn-action" onclick="openModal('edit', <?php echo $abs['id']; ?>, '<?php echo addslashes($abs['reason']); ?>', '<?php echo $abs['start_date']; ?>', '<?php echo $abs['end_date']; ?>')">Bearbeiten</button>
                                    <button class="btn btn-sm btn-outline-danger btn-action" onclick="openModal('delete', <?php echo $abs['id']; ?>)">Löschen</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content bg-dark text-white border border-secondary"><div class="modal-header border-secondary"><h5 class="modal-title" id="modalTitle"></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body" id="modalBody"></div></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
setTimeout(() => { const b = document.getElementById('statusBanner'); if (b) b.style.display = 'none'; }, 5000);
function openModal(mode, id, reason = '', start = '', end = '') {
    const title = document.getElementById('modalTitle');
    const body = document.getElementById('modalBody');
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    if (mode === 'add'){
        title.innerText = 'Neue Abmeldung';
        body.innerHTML = '<form action="/" method="POST"><label>Starttag</label><input type="date" name="start_date" class="form-control bg-dark text-white" required><label>Endtag</label><input type="date" name="end_date" class="form-control bg-dark text-white" required><label>Grund</label><textarea name="reason" class="form-control bg-dark text-white" rows="3" required></textarea><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button type="submit" name="add_absence" class="btn btn-primary">Speichern</button></form>';
    } else if (mode === 'edit') {
        title.innerText = 'Bearbeiten';
        body.innerHTML = `<form action="/" method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="${id}"><label>Start:</label><input type="date" class="form-control bg-dark text-white mb-2" name="start_date" value="${start}" required><label>Ende:</label><input type="date" class="form-control bg-dark text-white mb-2" name="end_date" value="${end}" required><label>Grund:</label><textarea class="form-control bg-dark text-white mb-3" name="reason" rows="3" required>${reason}</textarea><button type="submit" class="btn btn-primary w-100">Speichern</button></form>`;
    } else {
        title.innerText = 'Löschen?';
        body.innerHTML = `<p>Wirklich entfernen?</p><a href="?action=delete&id=${id}" class="btn btn-danger w-100">Löschen</a>`;
    }
    modal.show();
}
</script>
</body>
</html>