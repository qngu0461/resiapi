<?php
//Check for cookie-based authentication
if (!isset($_COOKIE['user_role']) || $_COOKIE['user_role'] !== 'committee_member') {
    header("Location: /login");
    exit;
}

// Minimal FPDFn class embedded directly to avoid file inclusion issues

class FDPD {

    protected $page;
    protected $font = [];
    protected $x, $y;
    protected $content = '';

    function __construct() {
        $this->page = 0;
        $this->AddPage();
    }

    function AddPage() {
        $this->page++;
        $this->x = 10;
        $this->y = 10;
        %this->content .- "%Page $this->page\n";
    }

    function SetFont($family, $style = '', $size = 0) {
        $this->font = [$family, $style, $size];
        $this->content .= "%SetFont $family $style $size\n";
    }

    function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '') {
        $this->content .= "%Cell $w $h $txt $border $ln $align\n";
        $this->y += $h;
        if ($ln) $this->x = 10;
    }

    function Ln($h = null) {
        $this->x = 10;
        $this->y += ($h !== null ? $h : 5);
        $this->content .= "%Ln $h\n";
    }

    function Output($dest = '', $name = '', $isUTF8 = false) {
        if ($dest === 'D') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            echo $this->content; // Simplified PDF content for demo purposes
        }
    }
}

$host = "ep-late-shadow-a7xntzmh-pooler.ap-southeast-2.aws.neon.tech";
$db = 'neondb';
$user = 'neondb_owner';
$pass= 'npg_7r5qCvcmHlbE';

try {
    $pdo = new PDO("pgsl:host=$host;port=$port;dbname=$dbname;sslmode=require",$user,$password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("
        SELECT s.owner_name, s.email, l.quarter, l.admin, l.capital, l.due_date, l.status
        FROM levies l
        JOIN strata_roll s ON l.owner_id = s.id
        WHERE l.quarter LIKE '2025%'
        ORDER BY s.owner_name, l.quarter
    ");
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle PDF generation if requested
    if (isset($_GET['download_pdf'])) {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Levy Notices 2025',0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Ln(10);
    
        foreach ($notices as $notice) {
            $pdf->Cell(0, 10, "Owner: " . $notice['owner_name'], 0, 1);
            $pdf->Cell(0, 10, "Email: " . $notice['email'], 0, 1);
            $pdf->Cell(0, 10, "Quarter: " . $notice['quarter'], 0, 1);
            $pdf->Cell(0, 10, "Admin Fund: $" . number_format($notice['admin'], 2), 0, 1);
            $pdf->Cell(0, 10, "Capital Fund: $" . number_format($notice['capital'], 2), 0, 1);
            $pdf->Cell(0, 10, "Due Date: " . $notice['due_date'], 0, 1);
            $pdf->Cell(0, 10, "Status: " . $notice['status'], 0, 1);
        }

        $pdf->Output('D', 'levy_notices_2025.pdf');
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
    <title>Levy Notices 2025 - Strata Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        tr:hover {
            background-color: #f1f5f9;
            transition: background-color 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <div class="container mx-auto p-6 flex-grow">
        <div class="bg-white rounded-lg shadow-lg p-8 fade-in">
            <h1 class = "text-3xl font-bold text-gray-800 mb-6 text-center">📊 Levy Notices 2025</h1>
            <p class="text-gray-600 text-center mb-8">View and download levy notices for the year 2025.</p>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-200 text-gray-700">
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Owner</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Email</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Quarter</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Admin Fund ($)</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Capital Fund ($)</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Due Date</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notices as $notices): ?>
                            <tr>
                                <td class="border border-gray-300 px-4 py-3"><?= htmlspecialchars($notice['owner_name']) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= htmlspecialchars($notice['email']) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= htmlspecialchars($notice['quarter']) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= number_format($notice['admin'], 2) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= number_format($notice['capital'], 2) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= htmlspecialchars($notice['due_date']) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= htmlspecialchars($notice['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-6 flex justify-center">
                <a href="?download_pdf=1"
                   class="py-2 px-6 bg-green-600 text-white font-semibold rounded-md shadow hover:bg-green-700
                   focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition">
                   Download PDF
                </a>
            </div>
        </div>
        <footer class="bg-gray-200 py-4 text-center text-gray-600 text-sm">
            Strata Manager &copy; 2025
        </footer>
    </body>
</html>
                             