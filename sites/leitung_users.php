<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once '../assets/db.php';
require_once '../assets/functions.php';

$my_id = $_SESSION['user_id'];
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $my_id"));
$is_leitung = ($me['role_id'] >= 2 || $me['is_admin'] == 1);
if (!$is_leitung) { die("Keine Berechtigung."); }

if (isset($_POST['create_user'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $discordname = mysqli_real_escape_string($conn, $_POST['discord-name']);
    $vorname = mysqli_real_escape_string($conn, $_POST['vorname']);
    $joined = mysqli_real_escape_string($conn, $_POST['joined_at']);
    $birthday = mysqli_real_escape_string($conn, $_POST['birthday']);
    $role_id = (int)$_POST['role_id'];
    $internal_title = mysqli_real_escape_string($conn, $_POST['internal_title']);
    $workarea = mysqli_real_escape_string($conn, $_POST['work_area']);
    $token = mysqli_real_escape_string($conn, $_POST['team_token']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    
    $allowed = ($me['is_admin'] == 1) || ($me['role_id'] == 3 && $role_id < 3) || ($me['role_id'] == 2 && $role_id == 1);

    if ($allowed) {
        mysqli_query($conn, "INSERT INTO users (username, discord_name, vorname, team_token, password, role_id, internal_title, work_area, joined_at, birthday) VALUES ('$username', '$discordname', '$vorname', '$token', '$password', $role_id, '$internal_title', '$work_area', '$joined', '$birthday')");
        logAction($conn, $my_id, mysqli_insert_id($conn), "User Erstellt", "Benutzer $username angelegt.");
        $_SESSION['msg'] = "Mitglied erfolgreich hinzugefügt.";
    }
    header("Location: leitung_teammitglieder"); exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Mitgliederverwaltung</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles/leitung_teammitglieder.css">
    <style>
        .form-control::placeholder { color: #adb5bd !important; opacity: 1; }
    </style>
</head>
<body>

<?php include '../assets/navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Mitgliederverwaltung</h2>
        <button class="btn btn-primary" onclick="openModal()">+ Neues Mitglied</button>
    </div>

    <div class="card bg-dark border-secondary mb-4">
        <div class="card-header border-secondary">Aktive Teammitglieder</div>
        <div class="card-body p-0">
            <table class="table table-dark table-hover mb-0">
                <thead><tr><th class="ps-3">Name</th><th>Status</th><th>Rang</th><th>System-Rolle</th><th>Aktionen</th></tr></thead>
                <tbody>
                <?php 
                $actives = mysqli_query($conn, "SELECT users.*, roles.role_name FROM users JOIN roles ON users.role_id = roles.id WHERE users.role_id != -1 ORDER BY username ASC");
                while($row = mysqli_fetch_assoc($actives)): ?>
                    <tr>
                        <td class="ps-3"><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><p><?php echo ((mysqli_num_rows(mysqli_query($conn, "SELECT id FROM absences WHERE user_id = " . $row['id'] . " AND CURDATE() >= start_date AND CURDATE() <= end_date")) > 0) ? '<span class="text-warning">Abgemeldet</span>' : '<span class="text-success">Nicht Abgemeldet</span>'); ?></p></td>
                        <td><?php echo htmlspecialchars($row['internal_title']); ?></td>
                        <td><?php echo $row['role_name']; ?></td>
                        <td><a href="leitung_user_detail?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info">Akte</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card bg-dark border-secondary">
        <div class="card-header border-secondary text-secondary">Ehemalige Teammitglieder (Archiv)</div>
        <div class="card-body p-0">
            <table class="table table-dark table-hover mb-0 text-secondary">
                <tbody>
                <?php 
                $ex = mysqli_query($conn, "SELECT id, username, internal_title FROM users WHERE role_id = -1 ORDER BY username ASC");
                while($row = mysqli_fetch_assoc($ex)): ?>
                    <tr>
                        <td class="ps-3"><?php echo htmlspecialchars($row['username']); ?> <em>(Ehemalig)</em></td>
                        <td><?php echo htmlspecialchars($row['internal_title']); ?></td>
                        <td style="width: 100px;"><a href="leitung_user_detail?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-secondary">Akte</a></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary text-white">
            <div class="modal-header border-secondary"><h5>Neues Mitglied</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form method="POST">
                    <label>Minecraft-Name</label><input type="text" name="username" class="form-control bg-dark text-white mb-2" placeholder="Minecraft-Name" required>
                    <label>Discord-Name</label><input type="text" name="discord-name" class="form-control bg-dark text-white mb-2" placeholder="Discord-Name" required>
                    <label>Vorname</label><input type="text" name="vorname" class="form-control bg-dark text-white mb-2" placeholder="Vorname" required>
                    <label>Geburtsdatum</label><input type="date" name="birthday" class="form-control bg-dark text-white mb-3" required>
                    <label>Beitrittsdatum</label><input type="date" name="joined_at" class="form-control bg-dark text-white mb-3" value="<?php echo date('Y-m-d'); ?>">
                    <label>System-Rang</label><select name="role_id" class="form-control bg-dark text-white mb-2">
                        <option value="1">Teammitglied</option>
                        <?php if ($me['role_id'] == 3 || $me['is_admin'] == 1): ?><option value="2">Bereichsleiter</option><?php endif; ?>
                        <?php if ($me['is_admin'] == 1): ?><option value="3">Teamleiter</option><?php endif; ?>
                    </select>
                    <label>Rang</label><input type="text" name="internal_title" class="form-control bg-dark text-white mb-2" placeholder="Interner Rang">
                    <label>(Co-)Aufgabenbereich</label><input type="text" name="work_area" class="form-control bg-dark text-white mb-2" placeholder="(Co-)Aufgabenbereich">
                    <label>Teamkennung</label><input type="text" name="team_token" class="form-control bg-dark text-white mb-2" placeholder="Teamkennung">
                    <label>Passwort</label><input type="password" name="password" class="form-control bg-dark text-white mb-2" placeholder="Passwort (Bei Leitern)"><br>
                    <button type="submit" name="create_user" class="btn btn-primary w-100">Hinzufügen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openModal() { new bootstrap.Modal(document.getElementById('userModal')).show(); }
</script>
</body>
</html>