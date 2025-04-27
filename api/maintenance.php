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

// Get search query from GET request
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Build the SQL query with full-text search
    $sql = "
        SELECT s.owner_name, m.description, m.status, m.created_at
        FROM maintenance_requests m
        JOIN strata_roll s ON s.owner_id = s.id
    ";
    if ($search_query) {
        $sql .= " WHERE m.search_vector @@ to_tsquery('english', :query)";
        $sql .= " ORDER BY ts_rank(m.search_vector, to_tsquery('english', :query)] DESC";
    } else {
        $sql .= " ORDER BY m.created_at DESC";
    }

    $stmt = $pdo->prepare($sql);
    if ($search_query) {
        $stmt->bindValue(':query', str_replace(' ', ' $ ',$search_query));
    }
    $stmt->execute();
    $request = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Requests - Strata Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transition: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        tr:hover {
            background-color: #f1f5f9;
            transition: background-color 0.3s ease;
        }
        .search-bar input:focus {
            outline: none;
            ring: 2px;
            ring-blue-500;
            border-color: #3b82f6;
        }
    </style>   
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <div class="container mx-auto p-6 flex-grow">
        <div class = "bg-white rounded-lg shadow-lg p-8 fade-in">
            <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">🛠️ Maintenance Requests</h1>
            <p class="text-gray-600 text-center mb-8">View and search maintenance requests submitted by owners.</p>
            <div class="search-bar mb-8 flex justify-center">
                <form method="GET" class="flex w-full max-w-md">
                    <input type="text" name="search" placeholder="Search maintenance requests..."
                           value="<? htmlspecialchars($search_query) ?>"
                           class="flex-grow px-4 py-2 border border-gray-300 rounded-l-md shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-r-md shadow hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition">
                        Search
                    </button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thdead>
                        <tr class="bg-gray-200 text-gray-700">
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Owner</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Description</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Status</th>
                            <th class="border border-gray-300 px-4 py-3 text-left font-semibold">Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td class="border border-gray-300 px-4 py-3"><?= htmlspecialchars($request['owner_name']) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= htmlspecialchars($request['description']) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= htmlspecialchars($request['status']) ?></td>
                                <td class="border border-gray-300 px-4 py-3"><?= htmlspecialchars($request['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <footer class="bg-gray-200 py-4 text-center text-gray-600 text-sm">
        Strata Manager ©️ 2025
    </footer>
</body>
</html>

