<?php
    $current = basename($_SERVER['PHP_SELF'], ".php");
    $jsonString = null;
    if ($current == 'index') {
        $jsonString = file_get_contents('./config.json');
    } else {
        $jsonString = file_get_contents('../config.json');
    }
    
    $config = json_decode($jsonString, true);

    $db_address = $config['database.address'];
    $db_database = $config['database.database'];
    $db_username = $config['database.username'];
    $db_password = $config['database.password'];

    $conn = mysqli_connect($db_address, $db_username, $db_password, $db_database);
    if (!$conn) die("Verbindung fehlgeschlagen: " . mysqli_connect_error());
?>