<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once '../assets/db.php';
require_once '../assets/functions.php';

$my_id = $_SESSION['user_id'];
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $my_id"));

$is_leitung = ($me['role_id'] >= 2 || $me['is_admin'] == 1);
if (!$is_leitung) { die("Keine Berechtigung."); }

$users_query = mysqli_query($conn, "SELECT id, username FROM users ORDER BY username ASC");
$user_options = "";
while($u = mysqli_fetch_assoc($users_query)) {
    $user_options .= "<option value='{$u['id']}'>" . htmlspecialchars($u['username']) . "</option>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $u_id = (int)$_POST['user_id'];
        $start = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end = mysqli_real_escape_string($conn, $_POST['end_date']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        mysqli_query($conn, "INSERT INTO absences (user_id, start_date, end_date, reason) VALUES ($u_id, '$start', '$end', '$reason')");
        logAction($conn, $my_id, NULL, "Abmeldung erstellt", "Mitglied: $u_id, Zeitraum: $start - $end");
    } elseif ($_POST['action'] == 'edit') {
        $id = (int)$_POST['id'];
        $start = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end = mysqli_real_escape_string($conn, $_POST['end_date']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        mysqli_query($conn, "UPDATE absences SET start_date = '$start', end_date = '$end', reason = '$reason' WHERE id = $id");
        logAction($conn, $my_id, NULL, "Abmeldung bearbeitet", "ID: $id");
    }
    header("Location: leitung_abmeldungen"); exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    mysqli_query($conn, "DELETE FROM absences WHERE id = " . (int)$_GET['id']);
    header("Location: leitung_abmeldungen"); exit;
}

$team_absences = mysqli_query($conn, "SELECT absences.*, users.username FROM absences JOIN users ON absences.user_id = users.id WHERE absences.end_date >= CURDATE() ORDER BY absences.start_date ASC");
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Leitung - Abmeldungen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles/index.css">
</head>
<body>
<?php include '../assets/navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Aktive Team-Abmeldungen</h2>
        <button class="btn btn-primary" onclick="openModal('add')">+ Hinzufügen</button>
    </div>

    <div class="card bg-dark border-secondary">
        <div class="card-body p-0">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 10%; padding-left: 20px;">Mitglied</th>
                        <th style="width: 16%;">Zeitraum</th>
                        <th>Grund</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 14%; text-align: right; padding-right: 20px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($abs = mysqli_fetch_assoc($team_absences)): 
                        $status = (strtotime($abs['start_date']) <= time()) ? "<span class='badge bg-success'>AKTIV</span>" : "<span class='badge bg-warning text-dark'>GEPLANT</span>";
                    ?>
                    <tr>
                        <td style="padding-left: 20px; font-weight: normal;"><?php echo htmlspecialchars($abs['username']); ?></td>
                        <td style="font-size: 0.9em;"><?php echo date('d.m.y', strtotime($abs['start_date'])) . ' - ' . date('d.m.y', strtotime($abs['end_date'])); ?></td>
                        <td><?php echo htmlspecialchars($abs['reason']); ?></td>
                        <td style="text-align: left;"><?php echo $status; ?></td>
                        <td style="text-align: right; padding-right: 20px; white-space: nowrap;">
                            <button class="btn btn-sm btn-outline-primary" onclick="openModal('edit', <?php echo $abs['id']; ?>, '<?php echo addslashes($abs['reason']); ?>', '<?php echo $abs['start_date']; ?>', '<?php echo $abs['end_date']; ?>')">Bearbeiten</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="openModal('delete', <?php echo $abs['id']; ?>)">Löschen</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content bg-dark text-white border-secondary"><div class="modal-header"><h5 class="modal-title" id="modalTitle"></h5></div><div class="modal-body" id="modalBody"></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal(mode, id='', reason='', start='', end='') {
    const b = document.getElementById('modalBody');
    const t = document.getElementById('modalTitle');
    if(mode === 'add') { t.innerText='Hinzufügen'; b.innerHTML=`<form action="leitung_abmeldungen" method="POST"><input type="hidden" name="action" value="add"><select name="user_id" class="form-control bg-dark text-white mb-2"><?php echo $user_options; ?></select><input type="date" name="start_date" class="form-control bg-dark text-white mb-2" required><input type="date" name="end_date" class="form-control bg-dark text-white mb-2" required><textarea name="reason" class="form-control bg-dark text-white mb-2" placeholder="Grund" required></textarea><button class="btn btn-primary w-100">Speichern</button></form>`; }
    else if(mode === 'edit') { t.innerText='Bearbeiten'; b.innerHTML=`<form action="leitung_abmeldungen" method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="${id}"><input type="date" name="start_date" class="form-control bg-dark text-white mb-2" value="${start}" required><input type="date" name="end_date" class="form-control bg-dark text-white mb-2" value="${end}" required><textarea name="reason" class="form-control bg-dark text-white mb-2" required>${reason}</textarea><button class="btn btn-primary w-100">Speichern</button></form>`; }
    else { t.innerText='Löschen?'; b.innerHTML=`<a href="?action=delete&id=${id}" class="btn btn-danger w-100">Wirklich löschen</a>`; }
    new bootstrap.Modal(document.getElementById('actionModal')).show();
}
</script>
</body>
</html>