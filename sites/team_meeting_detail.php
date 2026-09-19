<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once '../assets/db.php';
require_once '../assets/functions.php';
require_once '../assets/markdown.php';

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

if (!isset($_GET['id'])) { die("Keine Besprechungs-ID angegeben."); }
$meeting_id = (int)$_GET['id'];

$defaultTemplate = "## Themen\n### Vorschläge\n| Vorschlag | Von | Status |\n| --- | --- | --- |\n| Beispiel | Name | Offen |\n### Verbesserungen\n| Vorschlag | Von | Status |\n| --- | --- | --- |\n| Beispiel | Name | Offen |\n### System-Fehler\n| Fehler | Von | Status |\n| --- | --- | --- |\n| Beispiel | Name | Offen |\n## Ziele\n - Ziel 1 \n - Ziel 2\n## Upranks\n\n## Sonstiges\n";

$stmt = mysqli_prepare($conn, "SELECT tm.*, h.username AS host_name, pw.username AS protocol_writer_name, fb.username AS finalized_by_name FROM team_meetings tm LEFT JOIN users h ON tm.host_id = h.id LEFT JOIN users pw ON tm.protocol_writer_id = pw.id LEFT JOIN users fb ON tm.finalized_by = fb.id WHERE tm.id = ?");
mysqli_stmt_bind_param($stmt, "i", $meeting_id);
mysqli_stmt_execute($stmt);
$meeting = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$meeting) { die("Teambesprechung nicht gefunden."); }

$canEdit = ($me['is_admin'] == 1 || $me['role_id'] >= 2 || (int)$meeting['protocol_writer_id'] === $my_id);

$toastMessages = [
    'gespeichert'    => 'Protokoll gespeichert.',
    'fertiggestellt' => 'Protokoll fertiggestellt und gesendet.',
];
$toastText = null;
if (isset($_GET['msg']) && isset($toastMessages[$_GET['msg']])) {
    $toastText = $toastMessages[$_GET['msg']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_protocol']) || isset($_POST['finalize_protocol'])) && $canEdit) {
    $protocol_content = $_POST['protocol_content'];
    $summary = trim($_POST['summary']);
    $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
    $isFinalizing = isset($_POST['finalize_protocol']);

    if ($isFinalizing) {
        $stmt = mysqli_prepare($conn, "UPDATE team_meetings SET protocol_content=?, summary=?, end_time=?, last_updated_by=?, finalized_at=NOW(), finalized_by=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssiii", $protocol_content, $summary, $end_time, $my_id, $my_id, $meeting_id);
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE team_meetings SET protocol_content=?, summary=?, end_time=?, last_updated_by=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssii", $protocol_content, $summary, $end_time, $my_id, $meeting_id);
    }
    mysqli_stmt_execute($stmt);

    $attendance = $_POST['attendance'] ?? [];
    foreach ($attendance as $uid => $status) {
        $uid = (int)$uid;
        $status = ($status === 'anwesend') ? 'anwesend' : 'fehlend';
        $stmt2 = mysqli_prepare($conn, "INSERT INTO meeting_attendance (meeting_id, user_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
        mysqli_stmt_bind_param($stmt2, "iis", $meeting_id, $uid, $status);
        mysqli_stmt_execute($stmt2);
    }

    logActionLocal($conn, $meeting['protocol_writer_id'] ?? $meeting['host_id'], $isFinalizing ? 'MEETING_FINALIZE' : 'MEETING_PROTOCOL_EDIT', 'Teambesprechung ID ' . $meeting_id . ($isFinalizing ? ' fertiggestellt.' : ' Protokoll bearbeitet.'));

    if ($isFinalizing) {
        require_once '../assets/discord_webhook.php';
        $meetingDateFormatted = date('d.m.Y H:i', strtotime($meeting['meeting_date']));
        $msg = "Protokoll fertiggestellt: Teambesprechung vom {$meetingDateFormatted}\n" . ($summary !== '' ? $summary : '_Keine Zusammenfassung angegeben._');
        sendDiscordWebhook($msg);
    }

    header("Location: team_meeting_detail?id=$meeting_id&msg=" . ($isFinalizing ? 'fertiggestellt' : 'gespeichert'));
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id, username FROM users WHERE role_id != -1 ORDER BY username ASC");
mysqli_stmt_execute($stmt);
$activeUsers = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$stmt = mysqli_prepare($conn, "SELECT user_id, reason FROM meeting_absences WHERE meeting_id = ?");
mysqli_stmt_bind_param($stmt, "i", $meeting_id);
mysqli_stmt_execute($stmt);
$absenceRows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
$absences = [];
foreach ($absenceRows as $r) { $absences[(int)$r['user_id']] = $r['reason']; }

$stmt = mysqli_prepare($conn, "SELECT user_id, status FROM meeting_attendance WHERE meeting_id = ?");
mysqli_stmt_bind_param($stmt, "i", $meeting_id);
mysqli_stmt_execute($stmt);
$attendanceRows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
$attendanceMap = [];
foreach ($attendanceRows as $r) { $attendanceMap[(int)$r['user_id']] = $r['status']; }

$present = [];
$absentRegistered = [];
$missing = [];
foreach ($activeUsers as $u2) {
    $uid = (int)$u2['id'];
    if (isset($absences[$uid])) {
        $absentRegistered[] = $u2;
    } elseif (isset($attendanceMap[$uid]) && $attendanceMap[$uid] === 'anwesend') {
        $present[] = $u2;
    } else {
        $missing[] = $u2;
    }
}

$protocolContentForEditor = ($meeting['protocol_content'] !== null && trim($meeting['protocol_content']) !== '') ? $meeting['protocol_content'] : $defaultTemplate;
$renderedProtocol = renderMarkdown($meeting['protocol_content']);
?>
<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Teambesprechung vom <?php echo date('d.m.Y', strtotime($meeting['meeting_date'])); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #121212 !important; color: #fff; }
            .card-header { background-color: #333 !important; color: #fff !important; font-weight: bold; }
            .card-body { color: #ddd; }
            label { color: #ffffff !important; font-weight: 500; }
            .protocol-view table { color: #ccc; }
            .protocol-view { line-height: 1.6; }
            .attendance-badge { cursor: pointer; user-select: none; }
            .attendance-badge.locked { cursor: default; }
            .toolbar-btn { margin-right: 4px; margin-bottom: 6px; }
            .summary-text { white-space: pre-wrap; }
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
        <h2 class="mb-1 text-white">Teambesprechung vom <?php echo date('d.m.y H:i', strtotime($meeting['meeting_date'])); ?></h2>
        <a href="team_meetings" class="btn btn-sm btn-outline-light mb-3">Zurück zur Übersicht</a>
        <a href="team_meeting_print?id=<?php echo $meeting_id; ?>" target="_blank" class="btn btn-sm btn-outline-light mb-3">Als PDF</a>

        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary">Zusammenfassung</div>
            <div class="card-body">
                <?php if (!empty($meeting['summary'])): ?>
                    <p class="summary-text mb-0"><?php echo htmlspecialchars($meeting['summary']); ?></p>
                <?php else: ?>
                    <p class="text-muted mb-0">Noch keine Zusammenfassung vorhanden.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary">Informationen</div>
            <div class="card-body">
                <p><strong>Gesprächsleiter:</strong> <?php echo htmlspecialchars($meeting['host_name'] ?? '-'); ?></p>
                <p><strong>Protokollant:</strong> <?php echo htmlspecialchars($meeting['protocol_writer_name'] ?? '-'); ?></p>
                <p><strong>Ende:</strong> <?php echo $meeting['end_time'] ? date('H:i', strtotime($meeting['end_time'])) . ' Uhr' : '-'; ?></p>
                <p>
                    <strong>Status:</strong>
                    <?php if ($meeting['finalized_at']): ?>
                        <span class="badge bg-success">Fertiggestellt am <?php echo date('d.m.y H:i', strtotime($meeting['finalized_at'])); ?> von <?php echo htmlspecialchars($meeting['finalized_by_name'] ?? '-'); ?></span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">In Bearbeitung</span>
                    <?php endif; ?>
                </p>
                <div class="row">
                    <div class="col-md-4">
                        <h6>Anwesend</h6>
                        <?php foreach ($present as $p): ?><span class="badge bg-success me-1 mb-1"><?php echo htmlspecialchars($p['username']); ?></span><?php endforeach; ?>
                        <?php if (empty($present)): ?><span class="text-muted">-</span><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <h6>Abgemeldet</h6>
                        <?php foreach ($absentRegistered as $p): ?><span class="badge bg-warning text-dark me-1 mb-1" title="<?php echo htmlspecialchars($absences[(int)$p['id']]); ?>"><?php echo htmlspecialchars($p['username']); ?></span><?php endforeach; ?>
                        <?php if (empty($absentRegistered)): ?><span class="text-muted">-</span><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <h6>Fehlend</h6>
                        <?php foreach ($missing as $p): ?><span class="badge bg-secondary me-1 mb-1"><?php echo htmlspecialchars($p['username']); ?></span><?php endforeach; ?>
                        <?php if (empty($missing)): ?><span class="text-muted">-</span><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary">Protokoll</div>
            <div class="card-body">
                <div class="protocol-view" id="protocolView">
                    <?php echo $renderedProtocol; ?>
                </div>

                <?php if ($canEdit): ?>
                <button type="button" class="btn btn-sm btn-outline-primary mt-3" id="toggleEditBtn">Protokoll bearbeiten</button>

                <form method="POST" id="editForm" class="mt-3" style="display:none;">
                    <h6>Zusammenfassung</h6>
                    <div class="mb-3">
                        <textarea name="summary" class="form-control bg-dark text-white" rows="4" placeholder="Kurze Zusammenfassung, die Teammitglieder schnell durchlesen können..."><?php echo htmlspecialchars($meeting['summary'] ?? ''); ?></textarea>
                    </div>

                    <h6>Ende der Teambesprechung</h6>
                    <div class="mb-3">
                        <input type="time" name="end_time" class="form-control bg-dark text-white" style="max-width:200px;" value="<?php echo $meeting['end_time'] ? date('H:i', strtotime($meeting['end_time'])) : ''; ?>">
                    </div>

                    <h6>Anwesenheit erfassen</h6>
                    <p class="text-muted">Bereits abgemeldete Mitglieder werden automatisch übernommen und können hier nicht geändert werden.</p>
                    <div class="mb-3">
                        <?php foreach ($activeUsers as $u2):
                            $uid = (int)$u2['id'];
                            if (isset($absences[$uid])): ?>
                                <span class="badge bg-warning text-dark me-1 mb-1 attendance-badge locked" title="Abgemeldet: <?php echo htmlspecialchars($absences[$uid]); ?>"><?php echo htmlspecialchars($u2['username']); ?></span>
                            <?php else:
                                $status = $attendanceMap[$uid] ?? 'fehlend';
                            ?>
                                <span class="badge <?php echo $status === 'anwesend' ? 'bg-success' : 'bg-secondary'; ?> me-1 mb-1 attendance-badge" data-user-id="<?php echo $uid; ?>" data-status="<?php echo $status; ?>"><?php echo htmlspecialchars($u2['username']); ?></span>
                                <input type="hidden" name="attendance[<?php echo $uid; ?>]" value="<?php echo $status; ?>" id="attendanceInput<?php echo $uid; ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <h6>Protokolltext (Markdown)</h6>
                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-light toolbar-btn" data-md="**" data-md-end="**">Fett</button>
                        <button type="button" class="btn btn-sm btn-outline-light toolbar-btn" data-md="*" data-md-end="*">Kursiv</button>
                        <button type="button" class="btn btn-sm btn-outline-light toolbar-btn" data-md="## " data-md-line="1">Überschrift</button>
                        <button type="button" class="btn btn-sm btn-outline-light toolbar-btn" data-md="- " data-md-line="1">Liste</button>
                        <button type="button" class="btn btn-sm btn-outline-light toolbar-btn" data-md="1. " data-md-line="1">Nummerierte Liste</button>
                        <button type="button" class="btn btn-sm btn-outline-light toolbar-btn" id="insertTableBtn">Tabelle</button>
                        <button type="button" class="btn btn-sm btn-outline-light toolbar-btn" data-md="[Linktext](https://)" data-md-insert="1">Link</button>
                    </div>
                    <textarea name="protocol_content" id="protocolTextarea" class="form-control bg-dark text-white" rows="16"><?php echo htmlspecialchars($protocolContentForEditor); ?></textarea>

                    <button type="submit" name="save_protocol" class="btn btn-primary mt-3">Speichern</button>
                    <?php if (!$meeting['finalized_at']): ?>
                        <button type="submit" name="finalize_protocol" class="btn btn-success mt-3" onclick="return confirm('Protokoll fertigstellen und an Discord senden?');">Protokoll fertigstellen &amp; senden</button>
                    <?php endif; ?>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const toggleBtn = document.getElementById('toggleEditBtn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const form = document.getElementById('editForm');
                const view = document.getElementById('protocolView');
                if (form.style.display === 'none') {
                    form.style.display = 'block';
                    view.style.display = 'none';
                    toggleBtn.textContent = 'Bearbeitung schließen';
                } else {
                    form.style.display = 'none';
                    view.style.display = 'block';
                    toggleBtn.textContent = 'Protokoll bearbeiten';
                }
            });
        }

        document.querySelectorAll('.attendance-badge:not(.locked)').forEach(function(badge) {
            badge.addEventListener('click', function() {
                const uid = badge.getAttribute('data-user-id');
                const current = badge.getAttribute('data-status');
                const next = current === 'anwesend' ? 'fehlend' : 'anwesend';
                badge.setAttribute('data-status', next);
                badge.classList.remove('bg-success', 'bg-secondary');
                badge.classList.add(next === 'anwesend' ? 'bg-success' : 'bg-secondary');
                document.getElementById('attendanceInput' + uid).value = next;
            });
        });

        function insertAtCursor(textarea, before, after) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selected = textarea.value.substring(start, end);
            const newText = before + selected + (after || '');
            textarea.value = textarea.value.substring(0, start) + newText + textarea.value.substring(end);
            textarea.focus();
            textarea.selectionStart = start + before.length;
            textarea.selectionEnd = start + before.length + selected.length;
        }

        function insertAtLineStart(textarea, prefix) {
            const start = textarea.selectionStart;
            const value = textarea.value;
            const lineStart = value.lastIndexOf('\n', start - 1) + 1;
            textarea.value = value.substring(0, lineStart) + prefix + value.substring(lineStart);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + prefix.length;
        }

        document.querySelectorAll('.toolbar-btn[data-md]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const textarea = document.getElementById('protocolTextarea');
                const md = btn.getAttribute('data-md');
                if (btn.hasAttribute('data-md-line')) {
                    insertAtLineStart(textarea, md);
                } else if (btn.hasAttribute('data-md-insert')) {
                    insertAtCursor(textarea, md, '');
                } else {
                    insertAtCursor(textarea, md, btn.getAttribute('data-md-end') || '');
                }
            });
        });

        const insertTableBtn = document.getElementById('insertTableBtn');
        if (insertTableBtn) {
            insertTableBtn.addEventListener('click', function() {
                const textarea = document.getElementById('protocolTextarea');
                const table = "\n| Spalte 1 | Spalte 2 | Spalte 3 |\n| --- | --- | --- |\n| Wert | Wert | Wert |\n";
                insertAtCursor(textarea, table, '');
            });
        }

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