<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once '../assets/db.php';

$monate = [1=>'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

$my_id = $_SESSION['user_id'];
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $my_id"));

$is_leitung = ($me['role_id'] >= 2 || $me['is_admin'] == 1);

$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

$first_day_timestamp = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day_timestamp);
$blank_days = date('N', $first_day_timestamp) - 1;

function logActionLocal($conn, $target_id, $action_type, $details) {
    $performer_id = $_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "INSERT INTO action_logs (performer_id, target_id, action_type, details) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iiss", $performer_id, $target_id, $action_type, $details);
    mysqli_stmt_execute($stmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

$start_month_str = "$year-$month-01";
$end_month_str = "$year-$month-$days_in_month";

$abs_res = mysqli_query($conn, "SELECT absences.*, users.username FROM absences JOIN users ON absences.user_id = users.id WHERE absences.start_date <= '$end_month_str' AND absences.end_date >= '$start_month_str'");

$calendar_data = [];
while ($row = mysqli_fetch_assoc($abs_res)) {
    $calendar_data[] = $row;
}

$tb_res = mysqli_query($conn, "SELECT team_meetings.*, users.username AS protocol_writer_name FROM team_meetings LEFT JOIN users ON team_meetings.protocol_writer_id = users.id WHERE DATE(team_meetings.meeting_date) BETWEEN '$start_month_str' AND '$end_month_str'");

$meetings_data = [];
while ($row = mysqli_fetch_assoc($tb_res)) {
    $meetings_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Team-Kalender</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles/kalender.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .tb-badge {
            display: block;
            background-color: #0d6efd;
            color: #fff;
            border-radius: 4px;
            padding: 2px 4px;
            margin-top: 2px;
            font-size: 0.75rem;
            text-decoration: none;
        }
        .tb-badge:hover {
            background-color: #0b5ed7;
            color: #fff;
        }
    </style>
</head>
<body>

<?php include '../assets/navbar.php'; ?>

<div class="container-fluid mt-4 px-4">
    <div class="card calendar-card border-0">
        <div class="card-header calendar-header d-flex justify-content-between align-items-center">
            <div class="d-flex gap-2">
                <a href="?m=<?php echo ($month==1?12:$month-1); ?>&y=<?php echo ($month==1?$year-1:$year); ?>" class="btn btn-sm btn-outline-secondary">« Vorherige</a>
                <a href="?m=<?php echo ($month==12?1:$month+1); ?>&y=<?php echo ($month==12?$year+1:$year); ?>" class="btn btn-sm btn-outline-secondary">Nächster »</a>
            </div>

            <h4 class="m-0 text-center"><?php echo $monate[$month] . ' ' . $year; ?></h4>

            <div class="d-flex gap-2">
                <a href="team_meetings" class="btn btn-sm btn-outline-primary">Teambesprechungen</a>
                <button class="btn btn-sm btn-primary btn-action" onclick="openModal()">+ Neue Abmeldung</button>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table calendar-table mb-0">
                <thead>
                    <tr><th>Mo</th><th>Di</th><th>Mi</th><th>Do</th><th>Fr</th><th>Sa</th><th>So</th></tr>
                </thead>
                <tbody>
                    <tr>
                    <?php
                    for($i = 0; $i < $blank_days; $i++) { echo "<td></td>"; }

                    for($day = 1; $day <= $days_in_month; $day++) {
                        $current_date_str = sprintf("%04d-%02d-%02d", $year, $month, $day);
                        echo "<td><div class='day-num'>$day</div>";

                        foreach($calendar_data as $abs) {
                            if($current_date_str >= $abs['start_date'] && $current_date_str <= $abs['end_date']) {
                                $view_reason = ($is_leitung || $abs['user_id'] == $my_id) ? htmlspecialchars($abs['reason']) : "<em>Privat</em>";

                                $start_de = date('d.m.Y', strtotime($abs['start_date']));
                                $end_de = date('d.m.Y', strtotime($abs['end_date']));

                                $js_action = "showDetails('" . addslashes($abs['username']) . "', '{$start_de}', '{$end_de}', '" . addslashes($view_reason) . "');";
                                echo "<div onclick=\"$js_action\" class='abs-badge'>❌ {$abs['username']}</div>";
                            }
                        }

                        foreach($meetings_data as $tb) {
                            if (date('Y-m-d', strtotime($tb['meeting_date'])) === $current_date_str) {
                                $tb_time = date('H:i', strtotime($tb['meeting_date']));
                                echo "<a href='team_meeting_detail?id=" . $tb['id'] . "' class='tb-badge'>📅 {$tb_time} TB</a>";
                            }
                        }

                        echo "</td>";

                        if(($day + $blank_days) % 7 == 0) { echo "</tr><tr>"; }
                    }

                    $remaining_days = (7 - (($days_in_month + $blank_days) % 7)) % 7;
                    for($i = 0; $i < $remaining_days; $i++) { echo "<td></td>"; }
                    ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="absModal" tabindex="-1" aria-labelledby="absModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-white border border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title" id="absModalLabel">Abmeldungs-Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="absModalBody">
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content bg-dark text-white border border-secondary"><div class="modal-header border-secondary"><h5 class="modal-title" id="modalTitle"></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body" id="actionModalBody"></div></div></div>
</div>

<script>
function showDetails(username, start, end, reason) {
    document.getElementById('absModalBody').innerHTML =
        "<strong>Mitglied:</strong> " + username + "<br>" +
        "<strong>Zeitraum:</strong> " + start + " bis " + end + "<br><br>" +
        "<strong>Grund:</strong><br>" + reason;
    
    var myModal = new bootstrap.Modal(document.getElementById('absModal'));
    myModal.show();
}

function openModal() {
    const title = document.getElementById('modalTitle');
    const body = document.getElementById('actionModalBody');
    
    title.innerText = 'Neue Abmeldung';
    body.innerHTML = '<form action="kalender" method="POST">' +
        '<label>Starttag</label><input type="date" name="start_date" class="form-control bg-dark text-white" required>' +
        '<label>Endtag</label><input type="date" name="end_date" class="form-control bg-dark text-white" required>' +
        '<label>Grund</label><textarea name="reason" class="form-control bg-dark text-white" rows="3" required></textarea>' +
        '<div class="mt-3 text-end">' +
        '<button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Abbrechen</button>' +
        '<button type="submit" name="add_absence" class="btn btn-primary">Speichern</button>' +
        '</div></form>';
    
    var myModal = new bootstrap.Modal(document.getElementById('actionModal'));
    myModal.show();
}
</script>

</body>
</html>