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

if (!isset($_GET['id'])) { die("Keine User-ID angegeben."); }
$target_id = (int)$_GET['id'];
$self = 'leitung_user_detail';

function getRoleLabel($role_id) {
    $map = [
        -1 => 'Ehemaliges Teammitglied',
        1 => 'Teammitglied',
        2 => 'Bereichsleiter',
        3 => 'Teamleiter',
    ];
    return $map[$role_id] ?? 'Unbekannt';
}

function getAllowedRoles($me) {
    $roles = [-1 => 'Ehemaliges Teammitglied'];
    if ($me['is_admin'] == 1) {
        $roles[1] = 'Teammitglied';
        $roles[2] = 'Bereichsleiter';
        $roles[3] = 'Teamleiter';
    } elseif ($me['role_id'] == 3) {
        $roles[1] = 'Teammitglied';
        $roles[2] = 'Bereichsleiter';
    } elseif ($me['role_id'] == 2) {
        $roles[1] = 'Teammitglied';
    }
    ksort($roles);
    return $roles;
}

$toastMessages = [
    'stammdaten_gespeichert'   => 'Stammdaten gespeichert.',
    'strafe_hinzugefuegt'      => 'Strafe hinzugefügt.',
    'strafe_aktualisiert'      => 'Strafe aktualisiert.',
    'strafe_geloescht'         => 'Strafe gelöscht.',
    'abmeldung_hinzugefuegt'   => 'Abmeldung hinzugefügt.',
    'abmeldung_aktualisiert'   => 'Abmeldung aktualisiert.',
    'abmeldung_geloescht'      => 'Abmeldung gelöscht.',
];

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $target_id);
mysqli_stmt_execute($stmt);
$u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT users.*, roles.role_name FROM users JOIN roles ON users.role_id = roles.id WHERE users.id = " . $_GET['id']));

if (!$u) { die("Nutzer nicht gefunden."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['update_user_full'])) {
        $username = trim($_POST['username']);
        $discord_name = trim($_POST['discord_name']);
        $vorname = trim($_POST['vorname']);
        $internal_title = trim($_POST['internal_title']);
        $work_area = trim($_POST['work_area']);
        $birthday = $_POST['birthday'];
        $joined_at = $_POST['joined_at'];
        $team_token = trim($_POST['team_token']);
        $notes = trim($_POST['notes']);

        $allowedRoles = getAllowedRoles($me);
        $submitted_role = (int)$_POST['role_id'];
        $role_id = array_key_exists($submitted_role, $allowedRoles) ? $submitted_role : $u['role_id'];

        if ($me['is_admin'] == 1) {
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            $stmt = mysqli_prepare($conn, "UPDATE users SET username=?, discord_name=?, vorname=?, role_id=?, internal_title=?, work_area=?, team_token=?, notes=?, is_admin=?, joined_at=?, birthday=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sssissssissi", $username, $discord_name, $vorname, $role_id, $internal_title, $work_area, $team_token, $notes, $is_admin, $joined_at, $birthday, $target_id);
            logActionLocal($conn, $target_id, 'USER_UPDATE', 'Nutzerdaten für User ID ' . $target_id . ' aktualisiert. Username: ' .  $username . ', Discord-Name: ' . $discord_name . ', Vorname: ' . $vorname . ', Rolle-ID: ' .  $role_id. ', Internal-Title: ' .  $internal_title. ', (Co-)Aufgabenbereich: ' . $work_area . ', Team-Token:' .  $team_token. ', Notizen:' .  $notes. ', Is-Admin: ' . $is_admin . ', Joined-at: ' . $joined_at . ', Birthday: ' . $birthday);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET username=?, discord_name=?, vorname=?, role_id=?, internal_title=?, work_area=?, team_token=?, notes=?, joined_at=?, birthday=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "sssissssssi", $username, $discord_name, $vorname, $role_id, $internal_title, $work_area, $team_token, $notes, $joined_at, $birthday, $target_id);
            logActionLocal($conn, $target_id, 'USER_UPDATE', 'Nutzerdaten für User ID ' . $target_id . ' aktualisiert. Username: ' .  $username . ', Discord-Name: ' . $discord_name . ', Vorname: ' . $vorname . ', Rolle-ID: ' .  $role_id. ', Internal-Title: ' .  $internal_title. ', (Co-)Aufgabenbereich: ' . $work_area .  ', Team-Token:' .  $team_token. ', Notizen:' .  $notes. ', Joined-at: ' . $joined_at . ', Birthday: ' . $birthday );
        }
        mysqli_stmt_execute($stmt);

        if (!empty($_POST['password'])) {
            $password = password_hash(trim($_POST['password']), PASSWORD_BCRYPT);
            $stmt2 = mysqli_prepare($conn, "UPDATE users SET password=? WHERE id=?");
            mysqli_stmt_bind_param($stmt2, "si", $password, $target_id);
            logActionLocal($conn, $target_id, 'USER_UPDATE', 'Passwort für User ID ' . $target_id . ' aktualisiert.');
            mysqli_stmt_execute($stmt2);
        }

        header("Location: $self?id=$target_id&msg=stammdaten_gespeichert");
        exit;
    }

    if (isset($_POST['delete_user'])) {
        $id = (int)$_POST['user_id'];
        function deleteFromTable($conn, $table, $id) {
            $stmt = mysqli_prepare($conn, "DELETE FROM $table WHERE user_id=?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        deleteFromTable($conn, 'absences', $id);
        deleteFromTable($conn, 'meeting_absences', $id);
        deleteFromTable($conn, 'meeting_attendance', $id);
        deleteFromTable($conn, 'punishments', $id);

        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        logActionLocal($conn, $target_id, 'USER_DELETE', 'User ID: ' . $_POST['user_id'] . ' gelöscht.');
        header("Location: $self?id=$target_id&msg=strafe_geloescht");
        exit;
    }

    if (isset($_POST['add_punishment'])) {
        $type = trim($_POST['type']);
        $reason = trim($_POST['reason']);
        $prooflink = trim($_POST['prooflink']);
        $authorid = trim($my_id);
        $created_at = $_POST['created_at'];
        $stmt = mysqli_prepare($conn, "INSERT INTO punishments (user_id, type, reason, proof_link, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssis", $target_id, $type, $reason, $prooflink, $authorid, $created_at);
        mysqli_stmt_execute($stmt);
        logActionLocal($conn, $target_id, 'PUNISHMENT_ADD', 'Typ: ' . $_POST['type'] . ' | Grund: ' . $_POST['reason']);
        header("Location: $self?id=$target_id&msg=strafe_hinzugefuegt");
        exit;
    }

    if (isset($_POST['edit_punishment'])) {
        $pid = (int)$_POST['punishment_id'];
        $type = trim($_POST['type']);
        $reason = trim($_POST['reason']);
        $prooflink = trim($_POST['prooflink']);
        $authorid = trim($my_id);
        $created_at = $_POST['created_at'];
        $stmt = mysqli_prepare($conn, "UPDATE punishments SET type=?, reason=?, proof_link=?, created_by=?, created_at=? WHERE id=? AND user_id=?");
        logActionLocal($conn, $target_id, 'PUNISHMENT_EDIT', 'Strafe ID ' . $_POST['punishment_id'] . ' bearbeitet.');
        mysqli_stmt_bind_param($stmt, "sssisii", $type, $reason, $prooflink, $authorid, $created_at, $pid, $target_id);
        mysqli_stmt_execute($stmt);
        header("Location: $self?id=$target_id&msg=strafe_aktualisiert");
        exit;
    }

    if (isset($_POST['delete_punishment'])) {
        $pid = (int)$_POST['punishment_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM punishments WHERE id=? AND user_id=?");
        logActionLocal($conn, $target_id, 'PUNISHMENT_DEL', 'Strafe ID ' . $_POST['punishment_id'] . ' gelöscht.');
        mysqli_stmt_bind_param($stmt, "ii", $pid, $target_id);
        mysqli_stmt_execute($stmt);
        header("Location: $self?id=$target_id&msg=strafe_geloescht");
        exit;
    }

    if (isset($_POST['add_absence'])) {
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = trim($_POST['reason']);
        $authorid = trim($my_id);
        $stmt = mysqli_prepare($conn, "INSERT INTO absences (user_id, start_date, end_date, reason, created_by) VALUES (?, ?, ?, ?, ?)");
        logActionLocal($conn, $target_id, 'ABSENCE_ADD', 'Zeitraum: ' . $_POST['start_date'] . ' bis ' . $_POST['end_date']);
        mysqli_stmt_bind_param($stmt, "isssi", $target_id, $start_date, $end_date, $reason, $authorid);
        mysqli_stmt_execute($stmt);
        header("Location: $self?id=$target_id&msg=abmeldung_hinzugefuegt");
        exit;
    }

    if (isset($_POST['edit_absence'])) {
        $aid = (int)$_POST['absence_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = trim($_POST['reason']);
        $authorid = trim($my_id);
        $stmt = mysqli_prepare($conn, "UPDATE absences SET start_date=?, end_date=?, reason=?, created_by=? WHERE id=? AND user_id=?");
        logActionLocal($conn, $target_id, 'ABSENCE_EDIT', 'Abmeldung ID ' . $_POST['absence_id'] . ' bearbeitet.');
        mysqli_stmt_bind_param($stmt, "sssiii", $start_date, $end_date, $reason, $authorid, $aid, $target_id);
        mysqli_stmt_execute($stmt);
        header("Location: $self?id=$target_id&msg=abmeldung_aktualisiert");
        exit;
    }

    if (isset($_POST['delete_absence'])) {
        $aid = (int)$_POST['absence_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM absences WHERE id=? AND user_id=?");
        logActionLocal($conn, $target_id, 'ABSENCE_DEL', 'Abmeldung ID ' . $_POST['absence_id'] . ' gelöscht.');
        mysqli_stmt_bind_param($stmt, "ii", $aid, $target_id);
        mysqli_stmt_execute($stmt);
        header("Location: $self?id=$target_id&msg=abmeldung_geloescht");
        exit;
    }
}

$stmt = mysqli_prepare($conn, "SELECT p.*, cu.username AS created_by_name FROM punishments p LEFT JOIN users cu ON p.created_by = cu.id WHERE p.user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $target_id);
mysqli_stmt_execute($stmt);
$punishments = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$stmt = mysqli_prepare($conn, "SELECT * FROM absences WHERE user_id = ? ORDER BY start_date ASC");
mysqli_stmt_bind_param($stmt, "i", $target_id);
mysqli_stmt_execute($stmt);
$absences = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$allowedRoles = getAllowedRoles($me);

$toastText = null;
if (isset($_GET['msg']) && isset($toastMessages[$_GET['msg']])) {
    $toastText = $toastMessages[$_GET['msg']];
}

function getStatusBadge($start, $end) {
    $today = date('Y-m-d');
    if ($end < $today) return "<span class='badge bg-secondary'>ABGELAUFEN</span>";
    if ($start <= $today) return "<span class='badge bg-success'>AKTIV</span>";
    return "<span class='badge bg-warning text-dark'>GEPLANT</span>";
}
?>
<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Akte: <?php echo htmlspecialchars($u['username']); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #121212 !important; color: #fff; }
            body, p, h1, h2, h3, h4, h5, h6 { color: #d1d1d1 !important; }
            .card-header { background-color: #333 !important; color: #fff !important; font-weight: bold; }
            .table { color: #ccc; table-layout: fixed; }
            .table td, .table th { vertical-align: middle; word-wrap: break-word; }
            label { color: #ffffff !important; font-weight: 500; }
            .status-cell { text-align: left; padding-left: 10px; }
            .actions-cell { text-align: right; padding-right: 10px; }
            .form-check-input:checked { background-color: #0d6efd; border-color: #0d6efd; }
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
        <h2 class="mb-4 text-white">Akte: <?php echo htmlspecialchars($u['username']); ?></h2>

        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary">Statistiken</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3"><label>Im Team seit</label><p><?php echo getTeamDuration($u['joined_at']); ?></p></div>
                    <div class="col-md-4 mb-3"><label>Status</label><p><?php echo ((mysqli_num_rows(mysqli_query($conn, "SELECT id FROM absences WHERE user_id = " . $_GET['id'] . " AND CURDATE() >= start_date AND CURDATE() <= end_date")) > 0) ? '<span class="text-warning">Abgemeldet</span>' : '<span class="text-success">Nicht Abgemeldet</span>'); ?></p></div>
                    <div class="col-md-4 mb-3"><label>Gesammte Abwesenheits</label><p><?php echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(DATEDIFF(end_date, start_date) + 1) as total FROM absences WHERE user_id = " . $_GET["id"]))['total'] ?? 0;?> Tage (<?php echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM meeting_absences WHERE user_id = " . $_GET["id"]))['total'] ?? 0;?> Teambesprechung(en))</p></div>
                </div>
                <div class="row">
                    <?php
                        $warns_cnt = 0; $warns_active_cnt = 0; $strikes_cnt = 0; $strikes_active_cnt = 0;
                        $pun_res = mysqli_query($conn, "SELECT * FROM punishments WHERE user_id = " . $_GET['id']);
                        while($p = mysqli_fetch_assoc($pun_res)) {
                            if ($p['type'] === 'Verwarnung')  {
                                $warns_cnt++;
                                $diff = (new DateTime($p['created_at']))->diff(new DateTime());
                                if (!($diff->y > 0 || $diff->m >= 3)) $warns_active_cnt++;
                            }
                            if ($p['type'] === 'Strike') {
                                $strikes_cnt++;
                                $diff = (new DateTime($p['created_at']))->diff(new DateTime());
                                if (!($diff->y > 0 || $diff->m >= 6)) $strikes_active_cnt++;
                            }
                        } 
                    ?>
                    <div class="col-md-4 mb-3"><label>Rang</label><p><?php echo htmlspecialchars($u['internal_title']); ?> (<?php echo htmlspecialchars($u['role_name']); ?>)</p></div>
                    <div class="col-md-4 mb-3"><label>Verwarnungen</label><p><?php echo '<span class="text-danger">' . $warns_active_cnt . ' Verwarnung(en) von ' . $warns_cnt . ' aktiv</span>'; ?></p></div>
                    <div class="col-md-4 mb-3"><label>Strikes</label><p><?php echo '<span class="text-danger">' . $strikes_active_cnt . ' Strike(s) von ' . $strikes_cnt . ' aktiv</span>'; ?></p></div>
                </div>
            </div>
        </div>

        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary">Stammdaten & Zugänge</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-4 mb-3"><label>Minecraft-Name</label><input type="text" name="username" class="form-control bg-dark text-white" value="<?php echo htmlspecialchars($u['username']); ?>"></div>
                        <div class="col-md-4 mb-3"><label>Discord-Name</label><input type="text" name="discord_name" class="form-control bg-dark text-white" value="<?php echo htmlspecialchars($u['discord_name']); ?>"></div>
                        <div class="col-md-4 mb-3"><label>Vorname</label><input type="text" name="vorname" class="form-control bg-dark text-white" value="<?php echo htmlspecialchars($u['vorname']); ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label>Geburtsdatum</label><input type="date" name="birthday" class="form-control bg-dark text-white" value="<?php echo date('Y-m-d', strtotime($u['birthday'])); ?>"></div>
                        <div class="col-md-4 mb-3"><label>Alter</label><p><?php echo getTeamDuration($u['birthday']); ?></p></div>
                        <div class="col-md-4 mb-3"><label>Beitrittsdatum</label><input type="date" name="joined_at" class="form-control bg-dark text-white" value="<?php echo date('Y-m-d', strtotime($u['joined_at'])); ?>"></div>

                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Rang (System)</label>
                            <select name="role_id" class="form-control bg-dark text-white">
                                <?php foreach ($allowedRoles as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php if((int)$u['role_id']===$val) echo 'selected'; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                                <?php if (!array_key_exists((int)$u['role_id'], $allowedRoles)): ?>
                                    <option value="<?php echo (int)$u['role_id']; ?>" selected disabled><?php echo getRoleLabel((int)$u['role_id']); ?> (keine Berechtigung zum Ändern)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3"><label>Interner Rang</label><input type="text" name="internal_title" class="form-control bg-dark text-white" value="<?php echo htmlspecialchars($u['internal_title']); ?>"></div>
                        <div class="col-md-4 mb-3"><label>(Co-)Aufgabenbereich</label><input type="text" name="work_area" class="form-control bg-dark text-white" value="<?php echo htmlspecialchars($u['work_area']); ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label>Teamkennung</label><input type="text" name="team_token" class="form-control bg-dark text-white" value="<?php echo htmlspecialchars($u['team_token']); ?>"></div>
                        <div class="col-md-4 mb-3"><label>Neues Passwort</label><input type="password" name="password" class="form-control bg-dark text-white" placeholder="********"></div>
                        <?php if ($me['is_admin'] == 1): ?>
                            <div class="col-md-4 mb-3">
                                <label>Administrator</label><br>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_admin" id="adminSwitch" <?php if($u['is_admin']==1) echo 'checked'; ?>>
                                    <label class="form-check-label" for="adminSwitch">Status aktiviert</label>
                                </div>
                            </div>
                        <?php else: ?> 
                            <div class="col-md-4 mb-3">
                                <label>Ausgeblendet</label><br>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="disabledSwitch" id="disabledSwitch" <?php if($u['is_admin']==1) echo 'checked'; ?> disabled>
                                    <label class="form-check-label" for="disabledSwitch" dis>aktiviert</label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>div>
                    <div class="mb-3"><label>Notizen</label><textarea name="notes" class="form-control bg-dark text-white" rows="3"><?php echo htmlspecialchars($u['notes']); ?></textarea></div>
                    <button type="submit" name="update_user_full" class="btn btn-primary">Stammdaten speichern</button>
                    <?php
                        if ($me['is_admin'] == 1 || $me['role_id'] == 3): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUser<?php echo $u['id']; ?>">Benutzer Löschen</button>
                        <?php endif; 
                    ?>
                </form>
            </div>
        </div>

        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                <span>Strafen</span>
                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addPunishmentModal">+ Hinzufügen</button>
            </div>
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 9%;">Typ</th>
                        <th style="width: 9%;">Datum</th>
                        <th style="width: 9%;">Ablauf</th>
                        <th>Grund</th>
                        <th style="width: 8%;">Beweis</th>
                        <th style="width: 14%;">Erstellt von</th>
                        <th style="width: 7%;" class="status-cell">Status</th>
                        <th style="width: 16%;" class="actions-cell">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($punishments as $p):
                        $created = strtotime($p['created_at']);
                        $months = ($p['type'] == 'Strike') ? 6 : 3;
                        $expires = strtotime("+$months months", $created);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['type']); ?></td>
                        <td><?php echo date('d.m.y', $created); ?></td>
                        <td><?php echo date('d.m.y', $expires); ?></td>
                        <td><?php echo htmlspecialchars($p['reason']); ?></td>
                        <td>
                            <?php if (!empty($p['proof_link'])): ?>
                                <a href="<?php echo htmlspecialchars($p['proof_link']); ?>" target="_blank" rel="noopener noreferrer">Link</a>
                            <?php else: ?>
                                <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['created_by_name'] ?? 'Unbekannt'); ?></td>
                        <?php
                            $endtime = null;
                            if ($p['type'] == "Verwarnung") {
                                $date = new DateTime($p['created_at']);
                                $date->modify('+3 months');
                                $endtime = $date->format('Y-m-d H:i:s');
                            } else if ($p['type'] == "Strike") {
                                $date = new DateTime($p['created_at']);
                                $date->modify('+6 months');
                                $endtime = $date->format('Y-m-d H:i:s');
                            }
                        ?>
                        <td class="status-cell"><?php echo getStatusBadge($p['created_at'], $endtime); ?></td>
                        <td class="actions-cell">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPunishment<?php echo $p['id']; ?>">Bearbeiten</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deletePunishment<?php echo $p['id']; ?>">Löschen</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                <span>Abmeldungen</span>
                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addAbsenceModal">+ Hinzufügen</button>
            </div>
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 15%;">Zeitraum</th>
                        <th>Grund</th>
                        <th style="width: 12%;" class="status-cell">Status</th>
                        <th style="width: 18%;" class="actions-cell">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($absences as $a): ?>
                    <tr>
                        <td><?php echo date('d.m.y', strtotime($a['start_date'])) . ' - ' . date('d.m.y', strtotime($a['end_date'])); ?></td>
                        <td><?php echo htmlspecialchars($a['reason']); ?></td>
                        <td class="status-cell"><?php echo getStatusBadge($a['start_date'], $a['end_date']); ?></td>
                        <td class="actions-cell">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAbsence<?php echo $a['id']; ?>">Bearbeiten</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAbsence<?php echo $a['id']; ?>">Löschen</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="deleteUser<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <form method="POST">
                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Benutzer löschen</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Soll der Benutzer (<?php echo htmlspecialchars($u['username']) . " - ID: " . htmlspecialchars($u['id']); ?> ) wirklich gelöscht werden?</p>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="delete_user" class="btn btn-danger">Löschen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addPunishmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <form method="POST">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Strafe hinzufügen</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Typ</label>
                            <select name="type" class="form-control bg-dark text-white" required>
                                <option value="Strike">Strike</option>
                                <option value="Verwarnung">Verwarnung</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Datum</label>
                            <input type="date" name="created_at" class="form-control bg-dark text-white" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Grund</label>
                            <textarea name="reason" class="form-control bg-dark text-white" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Beweis</label>
                            <input type="url" name="prooflink" class="form-control bg-dark text-white" required>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="add_punishment" class="btn btn-primary">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php foreach ($punishments as $p): ?>
    <div class="modal fade" id="editPunishment<?php echo $p['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <form method="POST">
                    <input type="hidden" name="punishment_id" value="<?php echo $p['id']; ?>">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Strafe bearbeiten</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Typ</label>
                            <select name="type" class="form-control bg-dark text-white" required>
                                <option value="Strike" <?php if($p['type']=='Strike') echo 'selected'; ?>>Strike</option>
                                <option value="Verwarnung" <?php if($p['type']=='Verwarnung') echo 'selected'; ?>>Verwarnung</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Datum</label>
                            <input type="date" name="created_at" class="form-control bg-dark text-white" value="<?php echo date('Y-m-d', strtotime($p['created_at'])); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Grund</label>
                            <textarea name="reason" class="form-control bg-dark text-white" rows="3" required><?php echo htmlspecialchars($p['reason']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Beweis</label>
                            <input type="url" name="prooflink" class="form-control bg-dark text-white" required value="<?php echo htmlspecialchars($p['proof_link']); ?>">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="edit_punishment" class="btn btn-primary">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deletePunishment<?php echo $p['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <form method="POST">
                    <input type="hidden" name="punishment_id" value="<?php echo $p['id']; ?>">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Strafe löschen</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Soll diese Strafe (<?php echo htmlspecialchars($p['type']); ?>, <?php echo date('d.m.y', strtotime($p['created_at'])); ?>) wirklich gelöscht werden?</p>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="delete_punishment" class="btn btn-danger">Löschen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="modal fade" id="addAbsenceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <form method="POST" id="addAbsenceForm" onsubmit="return handleAbsenceSubmit(event, null)">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Abmeldung hinzufügen</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Von</label>
                            <input type="date" name="start_date" class="form-control bg-dark text-white" required>
                        </div>
                        <div class="mb-3">
                            <label>Bis</label>
                            <input type="date" name="end_date" class="form-control bg-dark text-white" required>
                        </div>
                        <div class="mb-3">
                            <label>Grund</label>
                            <textarea name="reason" class="form-control bg-dark text-white" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="add_absence" class="btn btn-primary">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php foreach ($absences as $a): ?>
    <div class="modal fade" id="editAbsence<?php echo $a['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <form method="POST" onsubmit="return handleAbsenceSubmit(event, <?php echo (int)$a['id']; ?>)">
                    <input type="hidden" name="absence_id" value="<?php echo $a['id']; ?>">
                    <div class="modal-header border-secondary">
                    <h5 class="modal-title">Abmeldung bearbeiten</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Von</label>
                            <input type="date" name="start_date" class="form-control bg-dark text-white" value="<?php echo $a['start_date']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Bis</label>
                            <input type="date" name="end_date" class="form-control bg-dark text-white" value="<?php echo $a['end_date']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Grund</label>
                            <textarea name="reason" class="form-control bg-dark text-white" rows="3" required><?php echo htmlspecialchars($a['reason']); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="edit_absence" class="btn btn-primary">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteAbsence<?php echo $a['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <form method="POST">
                    <input type="hidden" name="absence_id" value="<?php echo $a['id']; ?>">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Abmeldung löschen</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Soll die Abmeldung vom <?php echo date('d.m.y', strtotime($a['start_date'])); ?> bis <?php echo date('d.m.y', strtotime($a['end_date'])); ?> wirklich gelöscht werden?</p>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" name="delete_absence" class="btn btn-danger">Löschen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="modal fade" id="overlapWarningModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Überschneidung mit bestehender Abmeldung</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Der gewählte Zeitraum überschneidet sich mit folgender(n) bestehenden Abmeldung(en):</p>
                    <ul id="overlapList"></ul>
                    <p class="mb-0">Möchtest du die Abmeldung trotzdem speichern?</p>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="btn btn-danger" id="overlapConfirmBtn">Trotzdem speichern</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const absencesData = <?php echo json_encode(array_map(function($a) {
            return [
                'id' => (int)$a['id'],
                'start_date' => $a['start_date'],
                'end_date' => $a['end_date'],
                'reason' => $a['reason'],
            ];
        }, $absences)); ?>;

        let pendingForm = null;

        function findOverlaps(start, end, excludeId) {
            return absencesData.filter(function(a) {
                if (excludeId !== null && a.id === parseInt(excludeId)) return false;
                return start <= a.end_date && a.start_date <= end;
            });
        }

        function showOverlapModal(overlaps, form) {
            pendingForm = form;
            const list = document.getElementById('overlapList');
            list.innerHTML = '';
            overlaps.forEach(function(a) {
                const li = document.createElement('li');
                li.textContent = a.start_date + ' - ' + a.end_date + (a.reason ? ' (' + a.reason + ')' : '');
                list.appendChild(li);
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('overlapWarningModal')).show();
        }

        function handleAbsenceSubmit(event, excludeId) {
            const form = event.target;

            if (form.dataset.skipOverlap === "1") {
                form.dataset.skipOverlap = "0";
                return true;
            }

            const start = form.querySelector('[name="start_date"]').value;
            const end = form.querySelector('[name="end_date"]').value;
            if (!start || !end) return true;

            const overlaps = findOverlaps(start, end, excludeId);
            if (overlaps.length > 0) {
                event.preventDefault();
                showOverlapModal(overlaps, form);
                return false;
            }
            return true;
        }

        document.getElementById('overlapConfirmBtn').addEventListener('click', function() {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('overlapWarningModal')).hide();
            if (pendingForm) {
                pendingForm.dataset.skipOverlap = "1";
                const submitBtn = pendingForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.click();
                } else {
                    pendingForm.submit();
                }
                pendingForm = null;
            }
        });

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