<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once '../assets/db.php';

$my_id = $_SESSION['user_id'];
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $my_id"));

if (!$me || $me['is_admin'] != 1) { die("Zugriff verweigert."); }

$where_clauses = [];
if (!empty($_GET['filter_performer'])) {
    $perf_id = (int)$_GET['filter_performer'];
    $where_clauses[] = "action_logs.performer_id = $perf_id";
}
if (!empty($_GET['search_text'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search_text']);
    $where_clauses[] = "(action_logs.details LIKE '%$search%' OR action_logs.action_type LIKE '%$search%')";
}
$where_str = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$logs_query = "SELECT action_logs.*, p.username AS von_user, t.username AS target_user 
               FROM action_logs 
               JOIN users p ON action_logs.performer_id = p.id 
               LEFT JOIN users t ON action_logs.target_id = t.id 
               $where_str ORDER BY action_logs.created_at DESC";

$logs = mysqli_query($conn, $logs_query);
$all_performers = mysqli_query($conn, "SELECT id, username FROM users ORDER BY username ASC");
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>System Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #121212 !important; color: #fff !important; }
        .card { background-color: #1e1e1e !important; border-color: #333; }
        
        .form-label { color: #fff !important; }
        
        .table { color: #ccc !important; }
        .table thead { background-color: #333 !important; color: #fff !important; }
        
        .table td, .table th { color: #fff !important; }
        .text-muted { color: #aaa !important; }
        
        .badge { font-size: 0.8em; }
    </style>
</head>
<body>
    <?php include '../assets/navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">System-Aktivitätslogs</h2>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Akteur</label>
                        <select name="filter_performer" class="form-select bg-dark text-white border-secondary">
                            <option value="">-- Alle Akteure --</option>
                            <?php while($p = mysqli_fetch_assoc($all_performers)): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo (isset($_GET['filter_performer']) && $_GET['filter_performer'] == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['username']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Textsuche</label>
                        <input type="text" name="search_text" class="form-control bg-dark text-white border-secondary" value="<?php echo isset($_GET['search_text']) ? htmlspecialchars($_GET['search_text']) : ''; ?>" placeholder="Suche in Details...">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">Filtern</button>
                        <a href="admin_logs" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-dark table-hover border border-secondary">
                <thead>
                    <tr>
                        <th>Zeitpunkt</th>
                        <th>Akteur</th>
                        <th>Betroffener</th>
                        <th>Aktion</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($logs) == 0): ?>
                        <tr><td colspan="5" class="text-center">Keine Logs gefunden.</td></tr>
                    <?php else: ?>
                        <?php while($l = mysqli_fetch_assoc($logs)): ?>
                            <tr>
                                <td><small class="text-muted"><?php echo $l['created_at']; ?></small></td>
                                <td><strong><?php echo htmlspecialchars($l['von_user']); ?></strong></td>
                                <td><?php echo htmlspecialchars($l['target_user'] ?? "-"); ?></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($l['action_type']); ?></span></td>
                                <td><code><?php echo htmlspecialchars($l['details']); ?></code></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>