<?php
header('Content_type: application.json');

$host = "ep-late-shadow-a7xntzmh-pooler.ap-southeast-2.aws.neon.tech"
$dbname = 'neondb';
$user = 'neondb_owner';
$password = 'npg_7r5qCvcmHlbE';
$port = '5432';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("
            SELECT quarter,
                   SUM(admin) as admin,
                   SUM(capital) as capital 
            FROM levies
            WHERE quarter LIKE '2025%'
            GROUP BY quarter
            ORDER BY quarter
    ");
    $levies = $stmt->fetchALL(PDO::FETCH_ASSOC);

    $result = [];
    $quarters = ['2025-Q1', '2025-Q2', '2025-Q3', '2025-Q4'];
    foreach ($quarters as $q) {
        $found = array_filter($levies, fn($levy) => $levy['quarter'] === $q);
        if ($found) {
            $found = array_values($found)[0];
            $result[] = [
                'quarter' => str_replace('2025-', '',$q),
                'admin' => (float)$found['admin'],
                'capital' => (float)$found['capital']
            ];
        } else {
            $result[] = [
                'quarter' => str_replace('2025-', '', $q),
                'admin' => 0.0,
                'capital' => 0.0
            ];
        }
    }

    echo json_encode($result);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}