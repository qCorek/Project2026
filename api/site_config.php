<?php
header("Content-Type: application/json");

echo json_encode([
    "ok" => true,
    "config" => [
        "discord_invite" => "https://discord.gg/"
    ]
]);