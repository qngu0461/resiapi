<?php
// /api/documents.php

// Handle preflight CORS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// PostgreSQL config (adjust these values)
$host = "ep-late-shadow-a7xntzmh-pooler.ap-southeast-2.aws.neon.tech";
$db = 'neondb';
$user = 'neondb_owner';
$pass= 'npg_7r5qCvcmHlbE';

// Use sslmode=require to avoid certificate file
$dsn = "pgsql:host=$host;port=5432;dbname=$db;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Example query: Get all documents
    $stmt = $pdo->query("SELECT * FROM documents");  
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "documents" => $documents
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "error" => "Database error: " . $e->getMessage()
    ]);
}
