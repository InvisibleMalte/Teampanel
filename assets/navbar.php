<?php
  if (session_status() === PHP_SESSION_NONE) {session_start();}
  require_once __DIR__ . '/db.php'; 

  $session_user_id = $_SESSION['user_id'] ?? 0;
  $username = 'Unbekannt'; $role_id = -1; $is_admin = 0; $internal_title = 'Teammitglied'; $role_name = 'Rang'; $role_color = '#AAAAAA';

  if ($session_user_id > 0) {
      $stmt = mysqli_prepare($conn, "SELECT u.username, u.role_id, u.is_admin, u.internal_title, r.role_name, r.color_code FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
      mysqli_stmt_bind_param($stmt, "i", $session_user_id);
      mysqli_stmt_execute($stmt);
      if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
          extract($row);
          $role_color = $color_code ?? '#AAAAAA';
      }
  }
  $current = basename($_SERVER['PHP_SELF'], ".php");
?>

  <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold" href="/" style="color: #FF5555;">Team <span class="text-white">Panel</span></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent"><span class="navbar-toggler-icon"></span></button>

      <div class="collapse navbar-collapse" id="navContent">
        <div class="navbar-nav mx-auto">
          <a class="nav-link <?= ($current == 'index') ? 'active fw-bold' : '' ?>" href="/">Startseite</a>
          <a class="nav-link <?= ($current == 'kalender') ? 'active fw-bold' : '' ?>" href="kalender">Kalender</a>
          <a class="nav-link <?= ($current == 'abmeldungen_all') ? 'active fw-bold' : '' ?>" href="meine_abmeldungen">Meine Abmeldungen</a>
          <a class="nav-link <?= ($current == 'meine_warns') ? 'active fw-bold' : '' ?>" href="meine_strafen">Meine Strafen</a>
          <a class="nav-link <?= ($current == 'team_meetings' || $current == 'team_meeting_detail') ? 'active fw-bold' : '' ?>" href="team_meetings">Teambesprechungen</a>

          <?php if ($role_id >= 2 || $is_admin == 1): ?>
          <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= ($current == 'leitung_abmeldungen' || $current == 'leitung_bewerbungssperren' || $current == 'leitung_teammitglieder' || $current == 'leitung_user_detail' || $current == 'leitung_meetings') ? 'active fw-bold' : '' ?>" href="#" data-bs-toggle="dropdown">Leitung</a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li><a class="dropdown-item <?= ($current == 'leitung_abmeldungen') ? 'active fw-bold' : '' ?>" href="leitung_abmeldungen">Alle Abmeldungen</a></li>
              <li><a class="dropdown-item <?= ($current == 'leitung_bewerbungssperren') ? 'active fw-bold' : '' ?>" href="leitung_bewerbungssperren">Bewerbungssperren</a></li>
              <li><a class="dropdown-item <?= ($current == 'leitung_teammitglieder' || $current == 'leitung_user_detail') ? 'active fw-bold' : '' ?>" href="leitung_teammitglieder">Teammitglieder</a></li>
              <li><a class="dropdown-item <?= ($current == 'leitung_meetings') ? 'active fw-bold' : '' ?>" href="leitung_meetings">Teambesprechungen</a></li>
            </ul>
          </div>
          <?php endif; ?>

          <?php if ($is_admin == 1): ?>
          <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= ($current == 'admin_logs' || $current == 'system') ? 'active fw-bold' : '' ?>" href="#" data-bs-toggle="dropdown">Admin</a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li><a class="dropdown-item <?= ($current == 'admin_logs') ? 'active fw-bold' : '' ?>" href="admin_logs">System-Logs</a></li>
              <li><a class="dropdown-item <?= ($current == 'system') ? 'active fw-bold' : '' ?>" href="admin_system">Einstellungen</a></li>
            </ul>
          </div>
          <?php endif; ?>
        </div>

        <div class="navbar-nav">
          <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
              <img src="https://mc-heads.net/avatar/<?= urlencode($username) ?>/" class="rounded me-2" style="width: 32px;">
              <?= htmlspecialchars($username) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end p-3" style="min-width: 220px;">
              <li class="text-center border-bottom border-secondary mb-2">
                <img src="https://mc-heads.net/head/<?= urlencode($username) ?>/" style="width: 48px;" class="mb-2">
                <p class="mb-0 fw-bold"><?= htmlspecialchars($username) ?></p>
                <span class="badge mt-1" style="background-color: <?= $role_color ?>; color:#000;"><?= $role_name ?></span>
                <br><span style="color: #eee;"><?= htmlspecialchars($internal_title) ?></span>
                <p></p>
              </li>
              <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">Passwort ändern</a></li>
              <li><a class="dropdown-item text-danger" href="logout">Ausloggen</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
      <div id="pwToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
          <div class="d-flex">
              <div class="toast-body" id="pwToastBody"></div>
              <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
          </div>
      </div>
  </div>

  <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content bg-dark text-white">
      <div class="modal-header"><h5 class="modal-title">Passwort ändern</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <form action="assets/update_password.php" method="POST">
        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Neues Passwort</label><input type="password" name="new_password" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Wiederholen</label><input type="password" name="confirm_password" class="form-control" required></div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="showPwToggle">
            <label class="form-check-label" for="showPwToggle">Passwort anzeigen</label>
          </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Speichern</button></div>
      </form>
    </div></div>
  </div>

  <script>
  document.getElementById('showPwToggle').addEventListener('change', function() {
      var type = this.checked ? 'text' : 'password';
      var modal = document.getElementById('changePasswordModal');
      modal.querySelector('input[name="new_password"]').type = type;
      modal.querySelector('input[name="confirm_password"]').type = type;
  });

  document.addEventListener('DOMContentLoaded', function() {
      var pwMessages = {
          success:  { text: 'Passwort erfolgreich geändert.', type: 'success' },
          mismatch: { text: 'Die Passwörter stimmen nicht überein.', type: 'danger' },
          short:    { text: 'Das Passwort muss mindestens 6 Zeichen lang sein.', type: 'danger' },
          empty:    { text: 'Bitte ein neues Passwort angeben.', type: 'danger' }
      };
      var params = new URLSearchParams(window.location.search);
      var pwmsg = params.get('pwmsg');
      if (pwmsg && pwMessages[pwmsg]) {
          var info = pwMessages[pwmsg];
          var toastEl = document.getElementById('pwToast');
          toastEl.classList.remove('text-bg-success', 'text-bg-danger');
          toastEl.classList.add('text-bg-' + info.type);
          document.getElementById('pwToastBody').textContent = info.text;
          bootstrap.Toast.getOrCreateInstance(toastEl).show();
          params.delete('pwmsg');
          var newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
          window.history.replaceState({}, '', newUrl);
      }
  });
  </script>