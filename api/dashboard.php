<?php
require_once 'header.php';

$host = "ep-late-shadow-a7xntzmh-pooler.ap-southeast-2.aws.neon.tech";
$dbname = 'neondb';
$user = 'neondb_owner';
$password = 'npg_7r5qCvcmHlbE';
$port = '5432';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Buget Summary (Latest Quarter)
    $budgetStmt = $pdo->query("
        SELECT
            b.quarter,
            b.admin_target,
            b.capital_target,
            COALESCE(SUM(l.admin) FILTER (WHERE l.status = 'paid), 0) AS admin_collected,
            COALESCE(SUM(l.capital) FILTER (WHERE l.status = 'paid'), 0) AS capital_collected
        FROM budget b
        LEFT JOIN  levies l ON b.quarter = l.quarter
        GROUP BY b.quarter, b.admin_target, b.capital_target
        ORDER BY b.quarter DESC
        LIMIT 1
    ");
    $budgetSummary = $budgetStmt->fetch(PDO::FETCH_ASSOC);

    // Maintenance Requests (Recent 5)
    $maintenanceStmt = $pdo->query("
        SELECT s.owner_name, m.description, m.status, m.created_at
        FROM maintenance_requests m
        JOIN strata_roll s ON m.owner_id = s.id
        ORDER BY m.created_at DESC
        LIMIT 5
    ");
    $recentMaintenance = $maintenanceStmt->fetchAll(PDO::FETCH_ASSOC);

    // Levy Notices (Status Counts for 2025)
    $levyStmt = $pdo->query("
        SELECT l.status, COUNT(*) as count
        FROM levies l
        WHERE l.quarter LIKE '2025%'
        GROUP BY l.status
    ");
    $levyStatusCounts = array_column($levyStmt->fetchAll(PDO::FETCH_ASSOC), 'count', 'status');
    $levyStatusCounts = array_merge(['paid' => 0, 'pending' => 0, 'overdue' => 0], $levyStatusCounts);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<div class="fade-in">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">🏠 Dashboard</h1>
    <p class="text-gray-600 mb-8">Overview of Resilink management activities.</p>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Budget Summary (<?php echo htmlspecialchars($budgetSummary['quarter']); ?>)</h2>
            <p class="text-gray-600">Admin Target: $<?php echo number_format($budgetSummary['admin_target'], 2); ?></p>
            <p class="text-gray-600">Admin Collected: $<?php echo number_format($budgetSummary['admin_collected'], 2); ?></p>
            <p class="text-gray-600">Capital Target: $<?php echo number_format($budgetSummary['capital_target'], 2); ?></p>
            <p class="text-gray-600">Capital Collected: $<?php echo number_format($budgetSummary['capital_collected'], 2); ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Levy Payment Status (2025)</h2>
            <canvas id="levyStatusChart" class="w-full h-32"></canvas>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-gray-800 mg-4">Recent Maintenance Requests</h2>
            <ul class="space-y-2">
                <?php foreach ($recentMaintenance as $request): ?>
                    <li class="space-y-2">
                        <li class="font-medium"><?php echo htmlspecialchars($request['owner_name']); ?>:</span>
                        <?php echo htmlspecialchars($request['description']); ?> (<?php echo htmlspecialchars($request['status']); ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Budget Chart -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Budget Overview</h2>
        <canvas id="budgetChart" class="w-full h-64"></canvas>
    </div>
</div>

<script>
    // levy Status Chart
    const levyCtx = document.getElementById('levyStatusChart').getContext('2d');
    new Chart(levyCtx, {
        type: 'pie',
        data: {
            labels: ['Paid', 'Pending', 'Overdue'],
            datasets: [{
                data: [
                    <?php echo $levyStatusCounts['paid']; ?>,
                    <?php echo $levyStatusCounts['pending']; ?>
                    <?php echo $levyStatusCounts['overdue']; ?>
                ],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(255, 99, 132, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });

    // Budget Chart
    const budgetStmt = <?php echo json_encode([$budgetSummary]); ?>;
    const budgetCtx = document.getElementById('budgetChart').getContext('2d');
    new Chart(budgetCtx, {
        type: 'bar',
        data: {
            labels: budgetStmt.map(d => d.quarter),
            datasets [
                {
                label: 'Admin Target ($)',
                data: budgetStmt.map(d => d.admin_target),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
                },
                {
                    label: 'Admin Collected ($)',
                    data: budgetStmt.map(d => d.admin_collected),
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Capital Target ($)',
                    data: budgetStmt.map(d => d.capital_target),
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Capital Collected ($)',
                    data: budgetStmt.map(d => d.capital_collected),
                    backgroundColor: 'rgba(255, 159, 64, 0.5)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: "Amount ($)"
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
</script>

<?php require_once 'footer.php'; ?>
        

