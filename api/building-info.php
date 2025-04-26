<?php
header('Content-Type: application/json');

$host = "ep-late-shadow-a7xntzmh-pooler.ap-southeast-2.aws.neon.tech";
$dbname = 'neondb';
$user = 'neondb_owner';
$password = 'npg_7r5qCvcmHlbE';
$port = '5432';

try {
    $pdo = new PDO("pgsl:host=$host;port=$port;dbname=$dbname;sslmode=require",$user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT address, description, amenities, committee_details, last_updated FROM building_info ORDER BY id");
    $info = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($info);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
