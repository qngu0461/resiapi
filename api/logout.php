<?php
// Clear the user_role cookie
setcookie('user_role', '', time() - 3600, '/');
header('Location: /login.php');
exit;
?>