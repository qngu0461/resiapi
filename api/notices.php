<?php
//Check for cookie-based authentication
if (!isset($_COOKIE['user_role']) || $_COOKIE['user_role'] !== 'committee_member') {
    header("Location: /login");
    exit;
}

// Include FPDF library (you'll need to upload fpdf.php to your project)
require('fpdf.php');

$host = 'ep-late-shadow-a7xntzmh-pooler.ap-southeast-2.aws.neon.tech'
$dbname = 'neon';
$user = 'neondb_owner';
$password = 'npg_xGiQ5DLwfpN4';
$port = '5432';

try {
    $pdo = new PDO("pgsl:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("
        SELECT s.owner_name, s.email, l.quarter, l.admin, l.capital, l.due_date, l.status
        FROM levies l
        JOIN strata_roll s ON l.owner_id = s.id
        WHERE l.quarter LIKE '2025%'
        ORDER BY s.owner_name, l.quarter
    ");
    $notices = $stmt->fetchAll(PDO::Fetch_ASSOC);

    // Handle PDF generation if requested
    if (isset($_GET['download_pdf'])) {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10,'Levy Notices 2025', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Ln(10);

        foreach ($notices as $notice) {
            $pdf->Cell(0, 10, "Owner: " . $notices['owner_name'], 0, 1);
            $pdf->Cell(0, 10, "Email: " . $notices['email'], 0, 1);
            $pdf->Cell(0, 10, "Quarter:" . $notice['quarter'], 0, 1);
            $pdf->Cell(0, 10, "Admin Fund: $" . number_format($notice['admin'], 2), 0, 1);
            $pdf->Cell(0, 10, "Capital Fund: $" . number_format($notice['capital'], 2), 0, 1);
            $pdf->Cell(0, 10, "Due Date: " . $notice['due_date'], 0, 1);
            $pdf->Cell(0, 10, "Status: " . $notice['status'], 0, 1);
            $pdf->Ln(5);
        }

        $pdf->Output('D', 'Levy_notices_2025.pdf');
        exit;
    }
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Levy Notices 2025</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .download-btn { margin-top: 20px; padding: 10px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <h1> Levy Notices 2025 </h1>
    <table>
        <thead>
            <tr>
                <th>Owner</th>
                <th>Email</th>
                <th>Quarter</th>
                <th>Admin Fund ($) </th>
                <th>Capital Fund ($) </th>
                <th>Due Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notices as $notice): ?>
                <tr>
                    <td><?= htmlspecialchars($notice['owner_name']) ?></td>
                    <td><?= htmlspecialchars($notice['email']) ?></td>
                    <td><?= htmlspecialchars($notice['quarter']) ?></td>
                    <td><?= number_format($notice['admin'], 2) ?></td>
                    <td><?= number_format($notice['capital'], 2) ?></td>
                    <td><?= htmlspecialchars($notice['due_date']) ?></td>
                    <td><?= htmlspecialchars($notice['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <a href="?download_pdf=1" className="download-btn">Download PDF</a>
    </body>
    </html>
