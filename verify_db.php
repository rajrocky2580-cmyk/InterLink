<?php
$pdo = new PDO('mysql:host=localhost;dbname=InterLink;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "Tables: " . implode(', ', $tables) . "\n";
$users = $pdo->query("SELECT user_id, username, email, role, status FROM users")->fetchAll(PDO::FETCH_ASSOC);
echo "Users: " . json_encode($users, JSON_PRETTY_PRINT) . "\n";
echo "Total tables: " . count($tables) . "\n";
echo "Total users: " . count($users) . "\n";
