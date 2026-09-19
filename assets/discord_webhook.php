<?php
    $jsonString = file_get_contents('../config.json');
    $config = json_decode($jsonString, true);

    define('DISCORD_WEBHOOK_URL', $db_address = $config['discord.webhook']);

    function sendDiscordWebhook($content, $embeds = null) {
        if (DISCORD_WEBHOOK_URL === '') {
            return false;
        }

        $payload = ['content' => $content];
        if ($embeds !== null) {
            $payload['embeds'] = $embeds;
        }

        $ch = curl_init(DISCORD_WEBHOOK_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        $success = ($result !== false);
        curl_close($ch);
        return $success;
    }
?>