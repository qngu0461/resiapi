<?php
require_once 'header.php';

$host = "ep-late-shadow-a7xntzmh-pooler.ap-southeast-2.aws.neon.tech";
$dbname = 'neondb';
$user = 'neondb_owner';
$password = 'npg_7r5qCvcmHlbE';
$port = '5432';

$notification_sent = false;

// Pagination and filtering parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Number of records per page
$offset = ($page - 1) * $limit;
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $owner_name = $_POST['owner_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? '';

    // Simulate sending a notification (e.g., via email or database log)
    // In a real app, you would integrate with an email service or messaging system
    $notification_sent = true;
}

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Build the query for maintenance requests with filtering and pagination
    $sql = "
        SELECT s.owner_name, m.description, m.status, m.created_at
        FROM maintenance_requests m
        JOIN strata_roll s ON m.owner_id = s.id
    ";
    $count_sql = "
        SELECT COUNT(*)
        FROM maintenance_requests m
        JOIN strata_roll s ON m.owner_id = s.id
    ";
    $conditions = [];

    if ($search_query) {
        $conditions[] = "m.search_vector @@ to_tsquery('english', :query)";
    }
    if ($status_filter !== 'all') {
        $conditions[] = "m.status = :status";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
        $count_sql .= " WHERE " . implode(' AND ', $conditions);
    }

    $sql .= $search_query ? " ORDER BY ts_rank(m.search_vector, to_tsquery('english', :query)) DESC" : " ORDER BY m.created_at DESC";
    $sql .= " LIMIT :limit OFFSET :offset";

    // Get total number of records for pagination
    $count_stmt = $pdo->prepare($count_sql);
    if ($search_query) {
        $count_stmt->bindValue(':query', str_replace(' ', ' & ', $search_query));
    }
    if ($status_filter !== 'all') {
        $count_stmt->bindValue(':status', $status_filter, PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Validate page number
    if ($page < 1) {
        $page = 1;
        $offset = 0;
    } elseif ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    // Fetch maintenance requests for the current page
    $stmt = $pdo->prepare($sql);
    if ($search_query) {
        $stmt->bindValue(':query', str_replace(' ', ' & ', $search_query));
    }
    if ($status_filter !== 'all') {
        $stmt->bindValue(':status', $status_filter, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate status distribution for the pie chart
    $statusCountsStmt = $pdo->query("
        SELECT m.status, COUNT(*) as count
        FROM maintenance_requests m
        GROUP BY m.status
    ");
    $statusCounts = array_column($statusCountsStmt->fetchAll(PDO::FETCH_ASSOC), 'count', 'status');
    $statusCounts = array_merge(['open' => 0, 'in_progress' => 0, 'closed' => 0, 'overdue' => 0], $statusCounts);

    // Calculate requests per month for the line chart (for 2025)
    $monthlyCountsStmt = $pdo->query("
        SELECT 
            TO_CHAR(m.created_at, 'YYYY-MM') as month,
            COUNT(*) as request_count
        FROM maintenance_requests m
        WHERE EXTRACT(YEAR FROM m.created_at) = 2025
        GROUP BY TO_CHAR(m.created_at, 'YYYY-MM')
        ORDER BY TO_CHAR(m.created_at, 'YYYY-MM')
    ");
    $monthlyCounts = $monthlyCountsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for the line chart
    $months = [];
    $requestCounts = [];
    for ($month = 1; $month <= 12; $month++) {
        $monthStr = sprintf("2025-%02d", $month);
        $months[] = $monthStr;
        $found = array_filter($monthlyCounts, fn($entry) => $entry['month'] === $monthStr);
        $requestCounts[] = $found ? (int)array_values($found)[0]['request_count'] : 0;
    }
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<div class="bg-white rounded-lg shadow-lg p-8 fade-in">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center space-x-3">
        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
        <span>Maintenance Requests</span>
    </h1>
    <p class="text-gray-600 mb-8">View and search maintenance requests submitted by owners.</p>

    <!-- Notification Confirmation -->
    <?php if ($notification_sent): ?>
        <div class="mb-6 p-4 bg-green-100 text-green-800 rounded-md flex items-center space-x-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span>Notification sent successfully!</span>
        </div>
    <?php endif; ?>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Pie Chart -->
        <div class="flex flex-col items-center">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Status Distribution</h2>
            <div class="w-48 h-48">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- Line Chart -->
        <div class="flex flex-col items-center">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Requests Over Time (2025)</h2>
            <canvas id="requestsLineChart" class="w-full h-48"></canvas>
        </div>
    </div>

    <!-- Search Bar and Filter -->
    <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <!-- Search Bar -->
        <form method="GET" class="flex w-full max-w-md">
            <input type="text" name="search" placeholder="Search maintenance requests..." 
                   value="<?= htmlspecialchars($search_query) ?>"
                   class="flex-grow px-4 py-2 border border-gray-300 rounded-l-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
            <button type="submit" 
                    class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-r-md shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition">
                Search
            </button>
        </form>

        <!-- Filter Dropdown -->
        <form method="GET" class="flex items-center space-x-2">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
            <label for="status" class="text-gray-700">Filter by Status:</label>
            <select name="status" id="status" class="border rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
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

    <!-- Table -->
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
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Description</th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Status</th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Created At</th>
                    <th class="border border-gray-200 px-4 py-3 text-left font-semibold">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="5" class="border border-gray-200 px-4 py-3 text-center text-gray-500">No maintenance requests found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td class="border border-gray-200 px-4 py-3"><?= htmlspecialchars($request['owner_name']) ?></td>
                            <td class="border border-gray-200 px-4 py-3"><?= htmlspecialchars($request['description']) ?></td>
                            <td class="border border-gray-200 px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php
                                    switch ($request['status']) {
                                        case 'open':
                                            echo 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'in_progress':
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'closed':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'overdue':
                                            echo 'bg-red-100 text-red-800';
                                            break;
                                    }
                                    ?>">
                                    <?php echo htmlspecialchars($request['status']); ?>
                                </span>
                            </td>
                            <td class="border border-gray-200 px-4 py-3"><?= htmlspecialchars($request['created_at']) ?></td>
                            <td class="border border-gray-200 px-4 py-3">
                                <button onclick="openModal('<?php echo htmlspecialchars($request['owner_name']); ?>', '<?php echo htmlspecialchars($request['description']); ?>', '<?php echo htmlspecialchars($request['status']); ?>')"
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

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex justify-center space-x-2">
            <!-- Previous Button -->
            <a href="?page=<?php echo max(1, $page - 1); ?>&status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo htmlspecialchars($search_query); ?>"
               class="py-2 px-4 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>

            <!-- Page Numbers -->
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);

            if ($start_page > 1) {
                echo '<a href="?page=1&status=' . htmlspecialchars($status_filter) . '&search=' . htmlspecialchars($search_query) . '" class="py-2 px-4 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">1</a>';
                if ($start_page > 2) {
                    echo '<span class="py-2 px-4 text-gray-500">...</span>';
                }
            }

            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300';
                echo '<a href="?page=' . $i . '&status=' . htmlspecialchars($status_filter) . '&search=' . htmlspecialchars($search_query) . '" class="py-2 px-4 rounded-md ' . $active . '">' . $i . '</a>';
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="py-2 px-4 text-gray-500">...</span>';
                }
                echo '<a href="?page=' . $total_pages . '&status=' . htmlspecialchars($status_filter) . '&search=' . htmlspecialchars($search_query) . '" class="py-2 px-4 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">' . $total_pages . '</a>';
            }
            ?>

            <!-- Next Button -->
            <a href="?page=<?php echo min($total_pages, $page + 1); ?>&status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo htmlspecialchars($search_query); ?>"
               class="py-2 px-4 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 <?php echo $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                <svg class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Notification Modal -->
<div id="notificationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Send Notification</h2>
        <form method="POST">
            <input type="hidden" name="owner_name" id="modalOwnerName">
            <input type="hidden" name="description" id="modalDescription">
            <input type="hidden" name="status" id="modalStatus">
            <p class="text-gray-600 mb-4">Are you sure you want to send a notification to <span id="modalOwnerDisplay" class="font-medium"></span> about the following request?</p>
            <p class="text-gray-600 mb-2"><strong>Description:</strong> <span id="modalDescriptionDisplay"></span></p>
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
    function openModal(owner, description, status) {
        document.getElementById('modalOwnerName').value = owner;
        document.getElementById('modalDescription').value = description;
        document.getElementById('modalStatus').value = status;
        document.getElementById('modalOwnerDisplay').textContent = owner;
        document.getElementById('modalDescriptionDisplay').textContent = description;
        document.getElementById('modalStatusDisplay').textContent = status;
        document.getElementById('notificationModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('notificationModal').classList.add('hidden');
    }

    // Pie Chart for Status Distribution
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['Open', 'In Progress', 'Closed', 'Overdue'],
            datasets: [{
                data: [
                    <?php echo $statusCounts['open']; ?>,
                    <?php echo $statusCounts['in_progress']; ?>,
                    <?php echo $statusCounts['closed']; ?>,
                    <?php echo $statusCounts['overdue']; ?>
                ],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
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

    // Line Chart for Requests Over Time
    const lineCtx = document.getElementById('requestsLineChart').getContext('2d');
    new Chart(lineCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Requests Created',
                data: <?php echo json_encode($requestCounts); ?>,
                borderColor: 'rgba(54, 162, 235, 1)',
                fill: false,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Requests'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Month (2025)'
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