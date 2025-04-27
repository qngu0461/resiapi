<?php
require_once 'header.php';

// Database configuration
$host = "ep-late-shadow-a7xntzmh-pooler.ap-southeast-2.aws.neon.tech";
$dbname = 'neondb';
$user = 'neondb_owner';
$password = 'npg_7r5qCvcmHlbE';
$port = '5432';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
    ");
    $budgetData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<div class="bg-white rounded-lg shadow-lg p-8 fade-in">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center space-x-3">
        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span>Budget Management</span>
    </h1>
    <p class="text-gray-600 mb-8">Compare actual levy collections against budget targets with various visualizations.</p>

    <!-- Bar Chart -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Bar Chart: Budget Overview</h2>
        <canvas id="budgetBarChart" class="w-full h-64"></canvas>
    </div>

    <!-- Line Chart -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Line Chart: Budget Trends</h2>
        <canvas id="budgetLineChart" class="w-full h-64"></canvas>
    </div>

    <!-- Stacked Bar Chart -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Stacked Bar Chart: Targets vs Collected</h2>
        <canvas id="budgetStackedChart" class="w-full h-64"></canvas>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full border-collapse table-hover">
            <thead>
                <tr class="bg-gray-100 text-gray-700">
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold flex items-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span>Quarter</span>
                    </th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Admin Target ($)</th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Admin Collected ($)</th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Capital Target ($)</th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Capital Collected ($)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($budgetData as $data): ?>
                    <tr>
                        <td class="border border-gray-200 px-4 py-3"><?= htmlspecialchars($data['quarter']) ?></td>
                        <td class="border border-gray-200 px-4 py-3"><?= number_format($data['admin_target'], 2) ?></td>
                        <td class="border border-gray-200 px-4 py-3"><?= number_format($data['admin_collected'], 2) ?></td>
                        <td class="border border-gray-200 px-4 py-3"><?= number_format($data['capital_target'], 2) ?></td>
                        <td class="border border-gray-200 px-4 py-3"><?= number_format($data['capital_collected'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const budgetData = <?php echo json_encode($budgetData); ?>;

    // Bar Chart
    const barCtx = document.getElementById('budgetBarChart').getContext('2d');
    new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: budgetData.map(d => d.quarter),
            datasets: [
                {
                    label: 'Admin Target ($)',
                    data: budgetData.map(d => d.admin_target),
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Admin Collected ($)',
                    data: budgetData.map(d => d.admin_collected),
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Capital Target ($)',
                    data: budgetData.map(d => d.capital_target),
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Capital Collected ($)',
                    data: budgetData.map(d => d.capital_collected),
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
                        text: 'Amount ($)'
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

    // Line Chart
    const lineCtx = document.getElementById('budgetLineChart').getContext('2d');
    new Chart(lineCtx, {
        type: 'line',
        data: {
            labels: budgetData.map(d => d.quarter),
            datasets: [
                {
                    label: 'Admin Target ($)',
                    data: budgetData.map(d => d.admin_target),
                    borderColor: 'rgba(54, 162, 235, 1)',
                    fill: false,
                    tension: 0.1
                },
                {
                    label: 'Admin Collected ($)',
                    data: budgetData.map(d => d.admin_collected),
                    borderColor: 'rgba(75, 192, 192, 1)',
                    fill: false,
                    tension: 0.1
                },
                {
                    label: 'Capital Target ($)',
                    data: budgetData.map(d => d.capital_target),
                    borderColor: 'rgba(255, 99, 132, 1)',
                    fill: false,
                    tension: 0.1
                },
                {
                    label: 'Capital Collected ($)',
                    data: budgetData.map(d => d.capital_collected),
                    borderColor: 'rgba(255, 159, 64, 1)',
                    fill: false,
                    tension: 0.1
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
                        text: 'Amount ($)'
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

    // Stacked Bar Chart
    const stackedCtx = document.getElementById('budgetStackedChart').getContext('2d');
    new Chart(stackedCtx, {
        type: 'bar',
        data: {
            labels: budgetData.map(d => d.quarter),
            datasets: [
                {
                    label: 'Admin Target ($)',
                    data: budgetData.map(d => d.admin_target),
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    stack: 'Stack 0'
                },
                {
                    label: 'Capital Target ($)',
                    data: budgetData.map(d => d.capital_target),
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    stack: 'Stack 0'
                },
                {
                    label: 'Admin Collected ($)',
                    data: budgetData.map(d => d.admin_collected),
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    stack: 'Stack 1'
                },
                {
                    label: 'Capital Collected ($)',
                    data: budgetData.map(d => d.capital_collected),
                    backgroundColor: 'rgba(255, 159, 64, 0.5)',
                    stack: 'Stack 1'
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
                        text: 'Amount ($)'
                    },
                    stacked: true
                },
                x: {
                    stacked: true
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