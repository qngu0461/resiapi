<?php
// Check for cookie-based authentication
if (!isset($_COOKIE['user_role']) || $_COOKIE['user_role'] !== 'committee_member') {
    header('Location: /login');
    exit;
}

// Database configuration
$host = "ep-late-shadow-a7xntzmh-pooler.ap-southeast-2.aws.neon.tech";
$dbname = 'neondb';
$user = 'neondb_owner';
$password = 'npg_7r5qCvcmHlbE';
$port = '5432';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",$user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //Query to fetch budget data
    $stmt = $pdo->query("
        SELECT
            b.quarter,
            b.admin_target,
            b.capital_target,
            COALESCE(SUM(l.admin) FILTER (WHERE l.status = 'paid'), 0) AS admin_collected,
            COALESCE(SUM(l.capital) FILTER (WHERE l.status = 'paid'), 0) AS capital_collected
        FROM budget b
        LEFT JOIN levies l ON b.quarter = l.quarter
        GROUP BY b.quarter, b.admin_target, b.capital_target
        ORDER BY b.quarter 
    ")
    $budgetData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management - Strata Manager</title>
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
            <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">💸 Budget Management</h1>
            <p class="text-gray-600 text-center mb-8">Compare actual levy collections against budget targets.</p>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-200 text-gray-700">
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Quarter</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Admin Target ($)</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Admin Collected ($)</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Capital Target ($)</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Capital Collected ($)</th>
                        </tr>
                    </thead>
                    </tbody>
                        <?php foreach ($budgetData as $data): ?>
                            <tr>
                                <td class="border border-gray-300 px-4 py-3"><?= htmlspecialchars($data['quarter']) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= number_format($data['admin_target'], 2) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= number_format($data['admin_collected'], 2) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= number_format($data['capital_target'], 2) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= number_format($data['capital_collected'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <footer class="bd-gray-200 py-4 text-center text-gray-600 text-sm">
        Strata Manager ©️ 2025
    </footer>
</body>
</html>

