<?php
$host = "ep-late-shadow-a7xntzmh-pooler.ap-southeast-2.aws.neon.tech";
$dbname = 'neondb';
$user = 'neondb_owner';
$password = 'npg_7r5qCvcmHlbE';
$port = '5432';

if (isset($_GET['download_csv'])) {
    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "
            SELECT s.owner_name, s.email, l.quarter, l.admin, l.capital, l.due_date, l.status
            FROM levies l
            JOIN strata_roll s ON l.owner_id = s.id
            WHERE l.quarter LIKE '2025%'
            ORDER BY s.owner_name, l.quarter
        ";
        $stmt = $pdo->query($sql);
        $allNotices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="levy_notices_2025.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Owner', 'Email', 'Quarter', 'Admin Fund ($)', 'Capital Fund ($)', 'Due Date', 'Status']);

        foreach ($allNotices as $notice) {
            fputcsv($output, [
                $notice['owner_name'],
                $notice['email'],
                $notice['quarter'],
                number_format($notice['admin'], 2),
                number_format($notice['capital'], 2),
                $notice['due_date'],
                $notice['status']
            ]);
        }

        fclose($output);
        exit;
    } catch (PDOException $e) {
        die('Database error during CSV generation: ' . $e->getMessage());
    }
}

require_once 'header.php';

$notification_sent = false;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $owner_name = $_POST['owner_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $quarter = $_POST['quarter'] ?? '';
    $status = $_POST['status'] ?? '';

    $notification_sent = true;
}

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
        SELECT s.owner_name, s.email, l.quarter, l.admin, l.capital, l.due_date, l.status
        FROM levies l
        JOIN strata_roll s ON l.owner_id = s.id
        WHERE l.quarter LIKE '2025%'
    ";
    $count_sql = "
        SELECT COUNT(*)
        FROM levies l
        JOIN strata_roll s ON l.owner_id = s.id
        WHERE l.quarter LIKE '2025%'
    ";

    if ($status_filter !== 'all') {
        $sql .= " AND l.status = :status";
        $count_sql .= " AND l.status = :status";
    }

    $sql .= " ORDER BY s.owner_name, l.quarter LIMIT :limit OFFSET :offset";

    $count_stmt = $pdo->prepare($count_sql);
    if ($status_filter !== 'all') {
        $count_stmt->bindValue(':status', $status_filter, PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    if ($page < 1) {
        $page = 1;
        $offset = 0;
    } elseif ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if ($status_filter !== 'all') {
        $stmt->bindValue(':status', $status_filter, PDO::PARAM_STR);
    }
    $stmt->execute();
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statusCountsStmt = $pdo->query("
        SELECT l.status, COUNT(*) as count
        FROM levies l
        WHERE l.quarter LIKE '2025%'
        GROUP BY l.status
    ");
    $statusCounts = array_column($statusCountsStmt->fetchAll(PDO::FETCH_ASSOC), 'count', 'status');
    $statusCounts = array_merge(['paid' => 0, 'pending' => 0, 'overdue' => 0], $statusCounts);

    $quarterTotalsStmt = $pdo->query("
        SELECT 
            l.quarter,
            SUM(l.admin) AS total_admin,
            SUM(l.capital) AS total_capital
        FROM levies l
        WHERE l.quarter LIKE '2025%'
        GROUP BY l.quarter
        ORDER BY l.quarter
    ");
    $quarterTotals = $quarterTotalsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<div class="bg-white rounded-lg shadow-lg p-8 fade-in">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center space-x-3">
        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01m-.01 4h.01"></path>
        </svg>
        <span>Levy Notices 2025</span>
    </h1>
    <p class="text-gray-600 mb-8">View and download levy notices for the year 2025.</p>

    <?php if ($notification_sent): ?>
        <div class="mb-6 p-4 bg-green-100 text-green-800 rounded-md flex items-center space-x-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span>Notification sent successfully!</span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="flex flex-col items-center">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Status Distribution</h2>
            <div class="w-48 h-48">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <div class="flex flex-col items-center">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Funds per Quarter</h2>
            <canvas id="fundsBarChart" class="w-full h-48"></canvas>
        </div>
    </div>

    <div class="mb-6 flex justify-end">
        <form method="GET" class="flex items-center space-x-2">
            <label for="status" class="text-gray-700">Filter by Status:</label>
            <select name="status" id="status" class="border rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
            </select>
            <button type="submit" class="py-2 px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                <span>Filter</span>
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse table-hover">
            <thead>
                <tr class="bg-gray-100 text-gray-700">
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold flex items-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span>Owner</span>
                    </th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Email</th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Quarter</th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Admin Fund ($)</th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Capital Fund ($)</th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Due Date</th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Status</th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($notices)): ?>
                    <tr>
                        <td colspan="8" class="border border-gray-200 px-4 py-3 text-center text-gray-500">No levy notices found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($notices as $notice): ?>
                        <tr>
                            <td class="border border-gray-200 px-4 py-3"><?= htmlspecialchars($notice['owner_name']) ?></td>
                            <td class="border border-gray-200 px-4 py-3"><?= htmlspecialchars($notice['email']) ?></td>
                            <td class="border border-gray-200 px-4 py-3"><?= htmlspecialchars($notice['quarter']) ?></td>
                            <td class="border border-gray-200 px-4 py-3"><?= number_format($notice['admin'], 2) ?></td>
                            <td class="border border-gray-200 px-4 py-3"><?= number_format($notice['capital'], 2) ?></td>
                            <td class="border border-gray-200 px-4 py-3"><?= htmlspecialchars($notice['due_date']) ?></td>
                            <td class="border border-gray-200 px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php
                                    switch ($notice['status']) {
                                        case 'paid':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'pending':
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'overdue':
                                            echo 'bg-red-100 text-red-800';
                                            break;
                                    }
                                    ?>">
                                    <?php echo htmlspecialchars($notice['status']); ?>
                                </span>
                            </td>
                            <td class="border border-gray-200 px-4 py-3">
                                <button onclick="openModal('<?php echo htmlspecialchars($notice['owner_name']); ?>', '<?php echo htmlspecialchars($notice['email']); ?>', '<?php echo htmlspecialchars($notice['quarter']); ?>', '<?php echo htmlspecialchars($notice['status']); ?>')"
                                        class="py-1 px-3 bg-blue-500 text-white rounded-md hover:bg-blue-600 flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Notify</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex justify-center space-x-2">
            <a href="?page=<?php echo max(1, $page - 1); ?>&status=<?php echo htmlspecialchars($status_filter); ?>"
               class="py-2 px-4 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);

            if ($start_page > 1) {
                echo '<a href="?page=1&status=' . htmlspecialchars($status_filter) . '" class="py-2 px-4 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">1</a>';
                if ($start_page > 2) {
                    echo '<span class="py-2 px-4 text-gray-500">...</span>';
                }
            }

            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300';
                echo '<a href="?page=' . $i . '&status=' . htmlspecialchars($status_filter) . '" class="py-2 px-4 rounded-md ' . $active . '">' . $i . '</a>';
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="py-2 px-4 text-gray-500">...</span>';
                }
                echo '<a href="?page=' . $total_pages . '&status=' . htmlspecialchars($status_filter) . '" class="py-2 px-4 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">' . $total_pages . '</a>';
            }
            ?>

            <a href="?page=<?php echo min($total_pages, $page + 1); ?>&status=<?php echo htmlspecialchars($status_filter); ?>"
               class="py-2 px-4 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 <?php echo $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    <?php endif; ?>

    <div class="mt-6 flex justify-center">
        <a href="?download_csv=1" 
           class="py-2 px-6 bg-green-600 text-white font-semibold rounded-md shadow hover:bg-green-700 
           focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition flex items-center space-x-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            <span>Download CSV</span>
        </a>
    </div>
</div>

<div id="notificationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Send Notification</h2>
        <form method="POST">
            <input type="hidden" name="owner_name" id="modalOwnerName">
            <input type="hidden" name="email" id="modalEmail">
            <input type="hidden" name="quarter" id="modalQuarter">
            <input type="hidden" name="status" id="modalStatus">
            <p class="text-gray-600 mb-4">Are you sure you want to send a notification to <span id="modalOwnerDisplay" class="font-medium"></span> about their levy for <span id="modalQuarterDisplay"></span>?</p>
            <p class="text-gray-600 mb-2"><strong>Email:</strong> <span id="modalEmailDisplay"></span></p>
            <p class="text-gray-600 mb-4"><strong>Status:</strong> <span id="modalStatusDisplay"></span></p>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeModal()" class="py-2 px-4 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">Cancel</button>
                <button type="submit" name="send_notification" class="py-2 px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12h2a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v3a2 2 0 002 2h2m4 4v3m0 0l-3-3m3 3l3-3"></path>
                    </svg>
                    <span>Send</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(owner, email, quarter, status) {
        document.getElementById('modalOwnerName').value = owner;
        document.getElementById('modalEmail').value = email;
        document.getElementById('modalQuarter').value = quarter;
        document.getElementById('modalStatus').value = status;
        document.getElementById('modalOwnerDisplay').textContent = owner;
        document.getElementById('modalEmailDisplay').textContent = email;
        document.getElementById('modalQuarterDisplay').textContent = quarter;
        document.getElementById('modalStatusDisplay').textContent = status;
        document.getElementById('notificationModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('notificationModal').classList.add('hidden');
    }

    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['Paid', 'Pending', 'Overdue'],
            datasets: [{
                data: [
                    <?php echo $statusCounts['paid']; ?>,
                    <?php echo $statusCounts['pending']; ?>,
                    <?php echo $statusCounts['overdue']; ?>
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
                    position: 'bottom',
                }
            }
        }
    });

    const fundsBarCtx = document.getElementById('fundsBarChart').getContext('2d');
    const quarterTotals = <?php echo json_encode($quarterTotals); ?>;
    new Chart(fundsBarCtx, {
        type: 'bar',
        data: {
            labels: quarterTotals.map(d => d.quarter),
            datasets: [
                {
                    label: 'Admin Fund ($)',
                    data: quarterTotals.map(d => d.total_admin),
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Capital Fund ($)',
                    data: quarterTotals.map(d => d.total_capital),
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
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
</script>

<?php require_once 'footer.php'; ?>