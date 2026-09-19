<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once '../assets/db.php';

$my_id = $_SESSION['user_id'];
$sql = "SELECT p.*, u.username as author_name 
        FROM punishments p 
        LEFT JOIN users u ON p.created_by = u.id 
        WHERE p.user_id = $my_id 
        ORDER BY p.created_at ASC";
$punishments = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Meine Strafen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles/index.css">
    <link rel="stylesheet" href="../assets/styles/meine_warns.css">
</head>
<body>
    <?php include '../assets/navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">Deine Strafen</h2>
        <div class="card bg-dark border-secondary">
            <div class="card-body p-0">
                <table class="table table-dark table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="col-typ">Typ</th>
                            <th class="col-grund">Grund</th>
                            <th class="col-beweis">Beweis</th>
                            <th class="col-datum">Datum</th>
                            <th class="col-ablauf">Ablaufdatum</th>
                            <th class="col-author">Eingetragen von</th>
                            <th class="col-status">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($p = mysqli_fetch_assoc($punishments)): 
                            $created = new DateTime($p['created_at']);
                            $months = ($p['type'] === 'Warn') ? 3 : 6;
                            $expiry_date = (clone $created)->modify("+$months months");
                            $is_expired = (new DateTime() > $expiry_date);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['type']); ?></td>
                            <td><?php echo htmlspecialchars($p['reason']); ?></td>
                            <td><a href="<?php echo htmlspecialchars($p['proof_link']); ?>" target="_blank" class="text-info">Link</a></td>
                            <td><?php echo date('d.m.Y', strtotime($p['created_at'])); ?></td>
                            <td><?php echo $expiry_date->format('d.m.Y'); ?></td>
                            <td><?php echo htmlspecialchars($p['author_name'] ?? 'System'); ?></td>
                            <td>
                                <?php echo $is_expired ? '<span class="badge bg-secondary">Abgelaufen</span>' : '<span class="badge bg-success">Aktiv</span>'; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>