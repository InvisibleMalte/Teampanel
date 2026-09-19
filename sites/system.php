<?php
session_start();
require_once __DIR__ . '/../assets/db.php';

$user_id = $_SESSION['user_id'] ?? 0;
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();

if (!$me || $me['is_admin'] != 1) { die("Zugriff verweigert."); }
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>System Einstellungen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/styles/admin_system.css" rel="stylesheet">
    <style>
        body { background-color: #121212 !important; color: #fff !important; }
    </style>
</head>
<body>
    <?php include '../assets/navbar.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">System Einstellungen</h2>
        
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card admin-card p-3">
                    <h4>Datenbank Wartung</h4>
                    <p>Logs älter als 30 Tage bereinigen.</p>
                    <form action="actions/clear_logs.php" method="POST">
                        <button type="submit" class="btn btn-warning w-100">Logs leeren</button>
                    </form>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card admin-card p-3">
                    <h4>Admin-Rechte Verwaltung</h4>
                    <p>Einen Nutzer zum Administrator befördern.</p>
                    <form action="actions/promote_user.php" method="POST">
                        <select name="target_id" class="form-select bg-dark text-white border-secondary mb-2">
                            <?php 
                            $users = mysqli_query($conn, "SELECT id, username FROM users WHERE is_admin = 0");
                            while($u = mysqli_fetch_assoc($users)): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" class="btn btn-primary w-100">Admin-Status geben</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>