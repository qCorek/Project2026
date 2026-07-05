<?php
header("Content-Type: application/json");

echo json_encode([
  "ok" => true,
  "updates" => [
    [
      "version" => "1.0",
      "changes" => [
        "Gemaakt door ali en ro"
      ]
    ]
  ]
], JSON_PRETTY_PRINT);