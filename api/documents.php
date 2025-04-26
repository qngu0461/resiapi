<?php
// /api/documents/php

// Enable error reporting for debugging purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers to allow requests from the frontend (e.g, http://localhost:3000)
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json'); // Ensure the response is in JSON format

// Handle preflight OPTIONS request for CORS (required for some HTTP methods like POST)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); //Respond with 200 OK status
    exit(); // Exit to prevent further processing
}

// Since we're using a full connection string, we dont need to parse the .env file
// Connection string provided for Neon database
$connectionString = "postgresql://neondb_owner:npg_7r5qCvcmHlbE@ep-late-shadow-a7xntzmh-pooler.ap-southeast-2.aws.neon.tech/neondb?sslmode=require"
$user = "neondb_owner"; // Username from the connection string
$password = "npg_7r5qCvcmHlbE";

try {
    // Create a new PDO instance to connect to the PostgreSQL database using the connection string
    $pdo = new PDO(
        $connectionString,
        $user,
        $password
    );
    // Set PDO to throw exceptions on errors for better error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Execute a SQL query to fetch documents and related owner names from the database
    $stmt = $pdo -> query("
        SELECT d.id, d.name, d.file_path, d.upload_date, sr.owner_name AS uploaded_by
        FROM documents d
        JOIN strata_roll sr ON d.uploaded_by = sr.id
        ORDER BY d.upload_date DESC
    ");
    // Fetch all results as an associative array
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Encode the results as JSON and send them to the client
    echo json_encode($documents);
} catch (PDOException $e) {
    // If a database error occur, return a 500 status and the error message in JSON format
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);

}