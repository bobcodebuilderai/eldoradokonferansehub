<?php
/**
 * Login Page
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to admin dashboard
if (isLoggedIn()) {
    redirect('/admin/dashboard.php');
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        setFlashMessage('error', 'Vennligst fyll inn brukernavn og passord');
    } else {
        if (loginUser($username, $password)) {
            setFlashMessage('success', 'Velkommen tilbake!');
            redirect('/admin/dashboard.php');
        } else {
            setFlashMessage('error', 'Feil brukernavn eller passord');
        }
    }
}

$pageTitle = 'Logg inn - Eldorado Konferansehub';
?>
<!DOCTYPE html>
<html lang="no">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
</head>
<body class="bg-gradient-to-br from-blue-600 to-purple-700 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Eldorado</h1>
            <p class="text-gray-500 mt-2">Konferansehub</p>
        </div>
        
        <?php echo showFlashMessage(); ?>
        
        <form method="POST" action="" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Brukernavn eller e-post</label>
                <input type="text" id="username" name="username" required 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                       placeholder="Ditt brukernavn">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Passord</label>
                <input type="password" id="password" name="password" required 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                       placeholder="Ditt passord">
            </div>
            
            <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition duration-200 transform hover:scale-[1.02]">
                Logg inn
            </button>
        </form>
        
        <div class="mt-6 text-center text-sm text-gray-500">
            <p>Ikke registrert? <a href="/register.php" class="text-blue-600 hover:underline">Opprett konto</a></p>
        </div>
    </div>
</body>
</html>
