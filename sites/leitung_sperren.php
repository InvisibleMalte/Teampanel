<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once '../assets/db.php';
require_once '../assets/functions.php';

$my_id = $_SESSION['user_id'];
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $my_id"));

$is_leitung = ($me['role_id'] >= 2 || $me['is_admin'] == 1);
if (!$is_leitung) { die("Keine Berechtigung."); }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $mc_name = mysqli_real_escape_string($conn, $_POST['minecraft_name']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $area = mysqli_real_escape_string($conn, $_POST['area']);
        $expires = mysqli_real_escape_string($conn, $_POST['expires_at']);
        mysqli_query($conn, "INSERT INTO application_blocks (minecraft_name, reason, area, expires_at, created_by) VALUES ('$mc_name', '$reason', '$area', '$expires', $my_id)");
        logAction($conn, $my_id, NULL, "Sperre erstellt", "Name: $mc_name, Grund: $reason, Bis: $expires");
        $_SESSION['msg'] = "Sperre wurde erstellt.";
    } elseif ($_POST['action'] == 'edit') {
        $id = (int)$_POST['id'];
        $mc_name = mysqli_real_escape_string($conn, $_POST['minecraft_name']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $area = mysqli_real_escape_string($conn, $_POST['area']);
        $expires = mysqli_real_escape_string($conn, $_POST['expires_at']);
        mysqli_query($conn, "UPDATE application_blocks SET minecraft_name = '$mc_name', reason = '$reason', area = '$area', expires_at = '$expires' WHERE id = $id");
        logAction($conn, $my_id, NULL, "Sperre bearbeitet (ID: $id)", "Name: $mc_name, Grund: $reason, Bis: $expires");
        $_SESSION['msg'] = "Sperre ID $id wurde aktualisiert.";
    }
    header("Location: leitung_bewerbungssperren"); exit;
}

if (isset($_GET['action'])) {
    if ($_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $block = mysqli_fetch_assoc(mysqli_query($conn, "SELECT minecraft_name FROM application_blocks WHERE id = $id"));
        mysqli_query($conn, "DELETE FROM application_blocks WHERE id = $id");
        logAction($conn, $my_id, NULL, "Sperre gelöscht", "Sperre für " . $block['minecraft_name'] . " (ID: $id) entfernt.");
        $_SESSION['msg'] = "Sperre wurde gelöscht.";
    } elseif ($_GET['action'] == 'clear_expired') {
        mysqli_query($conn, "DELETE FROM application_blocks WHERE expires_at < CURDATE()");
        logAction($conn, $my_id, NULL, "Sperren bereinigt", "Alle abgelaufenen Sperren entfernt.");
        $_SESSION['msg'] = "Abgelaufene Sperren entfernt.";
    }
    header("Location: leitung_bewerbungssperren"); exit;
}

$blocks = mysqli_query($conn, "SELECT application_blocks.*, users.username AS leiter_name FROM application_blocks JOIN users ON application_blocks.created_by = users.id ORDER BY expires_at ASC");
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Bewerbungssperren</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles/index.css">
    <link rel="stylesheet" href="../assets/styles/leitung_sperren.css">
</head>
<body>

<?php include '../assets/navbar.php'; ?>

<div class="container mt-4">
    <?php if(isset($_SESSION['msg'])): ?>
        <div id="alertBanner" class="alert alert-info mb-4"><?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Bewerbungssperren</h2>
        <div>
            <a href="?action=clear_expired" class="btn btn-outline-secondary me-2">Abgelaufene löschen</a>
            <button class="btn btn-primary" onclick="openModal('add')">+ Neue Sperre</button>
        </div>
    </div>

    <div class="card bg-dark border-secondary">
        <div class="card-body p-0">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 12%; padding-left: 20px;">Minecraft-Name</th>
                        <th>Grund</th>
                        <th style="width: 9%;">Bereich</th>
                        <th style="width: 9%;">Gesperrt bis</th>
                        <th style="width: 15%;">Eingetragen von</th>
                        <th style="width: 8%;">Status</th>
                        <th style="width: 14%; text-align: right; padding-right: 20px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($b = mysqli_fetch_assoc($blocks)): 
                        $is_active = (strtotime($b['expires_at']) >= strtotime(date('Y-m-d')));
                    ?>
                    <tr>
                        <td style="padding-left: 20px; font-weight: normal;"><?php echo htmlspecialchars($b['minecraft_name']); ?></td>
                        <td><?php echo htmlspecialchars($b['reason']); ?></td>
                        <td><?php echo htmlspecialchars($b['area']); ?></td>
                        <td style="font-weight: normal;"><?php echo date('d.m.Y', strtotime($b['expires_at'])); ?></td>
                        <td style="font-size: 0.85em; opacity: 0.8;"><?php echo htmlspecialchars($b['leiter_name']); ?></td>
                        <td><span class="badge <?php echo $is_active ? 'bg-danger' : 'bg-secondary'; ?>"><?php echo $is_active ? "AKTIV" : "ABGELAUFEN"; ?></span></td>
                        <td style="text-align: right; padding-right: 20px; white-space: nowrap;">
                            <button class="btn btn-sm btn-outline-primary" onclick="openModal('edit', <?php echo $b['id']; ?>, '<?php echo addslashes($b['minecraft_name']); ?>', '<?php echo addslashes($b['reason']); ?>', '<?php echo addslashes($b['area']); ?>', '<?php echo $b['expires_at']; ?>')">Bearbeiten</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="openModal('delete', <?php echo $b['id']; ?>, '<?php echo addslashes($b['minecraft_name']); ?>')">Löschen</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content bg-dark text-white border-secondary"><div class="modal-header border-secondary"><h5 class="modal-title" id="modalTitle"></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body" id="modalBody"></div></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal(mode, id = '', name = '', reason = '', area = '', expires = '') {
    const title = document.getElementById('modalTitle');
    const body = document.getElementById('modalBody');
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    
    if (mode === 'add') {
        title.innerText = 'Neue Sperre';
        body.innerHTML = `<form action="leitung_bewerbungssperren" method="POST"><input type="hidden" name="action" value="add"><label>Name:</label><input type="text" name="minecraft_name" class="form-control bg-dark text-white mb-2" required><label>Grund:</label><textarea name="reason" class="form-control bg-dark text-white mb-2" required></textarea><label>Bereich:</label><input type="text" name="area" class="form-control bg-dark text-white mb-2" required><label>Bis:</label><input type="date" name="expires_at" class="form-control bg-dark text-white mb-3" required><button type="submit" class="btn btn-primary w-100">Speichern</button></form>`;
    } else if (mode === 'edit') {
        title.innerText = 'Bearbeiten';
        body.innerHTML = `<form action="leitung_bewerbungssperren" method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="${id}"><label>Name:</label><input type="text" name="minecraft_name" class="form-control bg-dark text-white mb-2" value="${name}" required><label>Grund:</label><textarea name="reason" class="form-control bg-dark text-white mb-2" required>${reason}</textarea><label>Bereich:</label><input type="text" name="area" class="form-control bg-dark text-white mb-2" value="${area}" required><label>Bis:</label><input type="date" name="expires_at" class="form-control bg-dark text-white mb-3" value="${expires}" required><button type="submit" class="btn btn-primary w-100">Speichern</button></form>`;
    } else {
        title.innerText = 'Löschen?';
        body.innerHTML = `<p>Soll die Sperre für <strong>${name}</strong> wirklich entfernt werden?</p><a href="?action=delete&id=${id}" class="btn btn-danger w-100">Jetzt löschen</a>`;
    }
    modal.show();
}

setTimeout(() => {
    const alert = document.getElementById('alertBanner');
    if (alert) { alert.style.transition = 'opacity 0.5s'; alert.style.opacity = '0'; setTimeout(() => alert.remove(), 500); }
}, 3000);
</script>
</body>
</html>