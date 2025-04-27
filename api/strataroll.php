<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$host = "ep-late-shadow-a7xntzmh-pooler.ap-southeast-2.aws.neon.tech";
$dbname = 'neondb';
$user = 'neondb_owner';
$password = 'npg_7r5qCvcmHlbE';
$port = '5432';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT id, ownder_name, email, unit_entilements FROM strata_roll ORDER BY owner_name");
    $roll = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($roll);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("error"=> 'Database error: ' .  $e->getMessage()));
}