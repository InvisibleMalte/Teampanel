<?php
    function getTeamDuration($joined_date) {
        $joined = new DateTime($joined_date);
        $now = new DateTime();

        if ($joined > $now) return "Zukunft";

        $interval = $joined->diff($now);

        $years = $interval->y;
        $months = $interval->m;
        $days = $interval->d;

        $output = [];
        if ($years > 0) {
            $output[] = $years . ($years == 1 ? " Jahr" : " Jahre");
        }
        if ($months > 0) {
            $output[] = $months . ($months == 1 ? " Monat" : " Monate");
        }
        if ($days > 0 || empty($output)) {
            $output[] = $days . ($days == 1 ? " Tag" : " Tage");
        }

        return implode(", ", $output);
    }

    function logAction($conn, $performer_id, $target_id, $action_type, $details) {
        $performer_id = (int)$performer_id;
        $target_id = $target_id ? (int)$target_id : "NULL";
        $action_type = mysqli_real_escape_string($conn, $action_type);
        $details = mysqli_real_escape_string($conn, $details);

        $sql = "INSERT INTO action_logs (performer_id, target_id, action_type, details) VALUES ($performer_id, $target_id, '$action_type', '$details')";
        mysqli_query($conn, $sql);
    }
?>