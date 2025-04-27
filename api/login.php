<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Simple authentication (replace with real logic)
    if ($username === 'committee' && $password === 'password123') {
        setcookie('user_role', 'committee_member', time() + 3600, '/');
        header('Location: /dashboard.php');
        exit;
    } else {
        $error = "Invalid credentials";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Strata Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <div class="flex justify-center mb-6">
            <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
        </div>
        <h1 class="text-2xl font-semibold text-gray-800 mb-6 text-center">Login to Resilink Manager</h1>
        <?php if (isset($error)) : ?>
            <p class="text-red-600 mb-4 text-center"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-4 relative">
                <label class="block text-gray-700 mb-2" for="username">Username</label>
                <div class="flex items-center border rounded-md">
                    <span class="px-3 text-gray-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </span>
                    <input type="text" name="username" id="username" class="w-full px-4 py-2 border-l-0 rounded-r-md focus:ring-2 focus:ring-blue-500" required>
                </div>
            </div>
            <div class="mb-6 relative">
                <label class="block text-gray-700 mb-2" for="password">Password</label>
                <div class="flex items-center border rounded-md">
                    <span class="px-3 text-gray-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.104-.896-2-2-2s-2 .896-2 2c0 .55.223 1.05.586 1.414C8.223 13.05 8 13.55 8 14v1h8v-1c0-.45-.223-.95-.586-1.586C15.777 12.05 16 11.55 16 11zm-2 0v2H8v-2h2zm2 6H8v-1h4v1z"></path>
                        </svg>
                    </span>
                    <input type="password" name="password" id="password" class="w-full px-4 py-2 border-l-0 rounded-r-md focus:ring-2 focus:ring-blue-500" required>
                </div>
            </div>
            <button type="submit" class="w-full py-2 bg-blue-600 text-white font-semibold rounded-md hover:bg-blue-700 flex items-center justify-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14"></path>
                </svg>
                <span>Login</span>
            </button>
        </form>
    </div>
</body>
</html>








