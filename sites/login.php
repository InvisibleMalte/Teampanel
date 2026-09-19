<?php
session_start();
require_once '../assets/db.php';

$error_msg = "";

function getIpInfo($ip) {
    if ($ip == '::1' || $ip == '127.0.0.1') return "Localhost";
    $query = @unserialize(file_get_contents("http://ip-api.com/php/$ip?fields=city,status"));
    return ($query && $query['status'] == 'success') ? $query['city'] : "Unbekannt";
}

if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $auth_value = mysqli_real_escape_string($conn, $_POST['auth_value']);

    $res = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");

    if (mysqli_num_rows($res) > 0) {
        $user = mysqli_fetch_assoc($res);

        if ($user['role_id'] == -1) {
            $error_msg = "Dein Account wurde deaktiviert, da du ein ehemaliges Teammitglied bist.";
        } else {
            $login_success = false;

            if (!empty($user['password'])) {
                if (password_verify($auth_value, $user['password'])) {
                    $login_success = true;
                } else {
                    $error_msg = "Falsches Passwort.";
                }
            } else {
                if ($auth_value === $user['team_token']) {
                    $login_success = true;
                } else {
                    $error_msg = "Ungültige Teamkennung.";
                }
            }



            if ($login_success) {
				$ip = $_SERVER['REMOTE_ADDR'];
				$city = getIpInfo($ip);

				$log_sql = "INSERT INTO action_logs (performer_id, target_id, action_type, details) VALUES (?, ?, ?, ?)";
				$log_stmt = mysqli_prepare($conn, $log_sql);

				$performer_id = (int)$user['id'];
				$target_id = (int)$user['id'];
				$action_type = 'LOGIN';
				$details = "Login von IP: " . $ip . " (" . $city . ")";

				mysqli_stmt_bind_param($log_stmt, "iiss", $performer_id, $target_id, $action_type, $details);
				mysqli_stmt_execute($log_stmt);
				mysqli_stmt_close($log_stmt);

				$_SESSION['user_id'] = $user['id'];
				$_SESSION['username'] = $user['username'];
				header("Location: /");
				exit;
			}
        }
    } else {
        $error_msg = "Benutzer wurde im System nicht gefunden.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Teampanel - Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background-color: #121212 !important;
                color: #fff;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-card {
                width: 100%;
                max-width: 400px;
            }
            .card-header {
                background-color: #333 !important;
                color: #fff !important;
                font-weight: bold;
            }
            label {
                color: #ffffff !important;
                font-weight: 500;
            }
            .form-check-label {
                color: #ccc !important;
            }
        </style>
    </head>
    <body>
        <div class="login-card">
            <div class="card bg-dark border-secondary">
                <div class="card-header border-secondary text-center">Teampanel Login</div>
                <div class="card-body">
                    <?php if (!empty($error_msg)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error_msg); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label>Minecraft-Name</label>
                            <input type="text" name="username" class="form-control bg-dark text-white" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label>Passwort oder Teamkennung</label>
                            <input type="password" name="auth_value" id="loginAuth" class="form-control bg-dark text-white" required>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="showAuth" onclick="document.getElementById('loginAuth').type = this.checked ? 'text' : 'password'">
                            <label class="form-check-label" for="showAuth">Anzeigen</label>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary w-100">Einloggen</button>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>