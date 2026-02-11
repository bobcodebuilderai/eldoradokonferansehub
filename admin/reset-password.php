<?php
/**
 * Reset Password Page
 */
$pageTitle = 'Reset Password';
require_once __DIR__ . '/../includes/functions.php';

// Handle language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], ['no', 'en'])) {
    setLanguage($_GET['lang']);
}
$currentLang = getCurrentLanguage();

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Verify token
$user = verifyResetToken($token);
if (!$user) {
    die(__('invalid_or_expired_token'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = __('csrf_error');
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || strlen($password) < 8) {
            $error = __('password_at_least_8_chars');
        } elseif ($password !== $confirmPassword) {
            $error = __('passwords_dont_match');
        } else {
            if (resetPassword($token, $password)) {
                $success = __('password_reset_successful');
            } else {
                $error = __('failed_to_reset_password');
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('reset_password'); ?> - <?php echo __('app_name'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md mx-4">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800"><?php echo __('reset_password'); ?></h1>
            <p class="text-gray-600 mt-2"><?php echo __('create_new_password'); ?></p>
            
            <!-- Language Switcher -->
            <div class="flex justify-center items-center space-x-2 mt-4">
                <a href="?token=<?php echo e($token); ?>&lang=no" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'no' ? 'bg-purple-600 text-white font-bold' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">Norsk</a>
                <a href="?token=<?php echo e($token); ?>&lang=en" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'en' ? 'bg-purple-600 text-white font-bold' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">English</a>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo e($success); ?>
            </div>
            <div class="text-center">
                <a href="index.php" class="gradient-bg text-white px-6 py-2 rounded-lg font-medium inline-block hover:opacity-90 transition">
                    <?php echo __('back_to_login'); ?>
                </a>
            </div>
        <?php else: ?>
            <form method="POST" action="" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('new_password'); ?></label>
                    <input type="password" id="password" name="password" required minlength="8"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        placeholder="<?php echo __('password'); ?> (min 8)">
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('confirm_password'); ?></label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        placeholder="<?php echo __('confirm_password'); ?>">
                </div>
                
                <button type="submit" 
                    class="w-full gradient-bg text-white font-semibold py-3 rounded-lg hover:opacity-90 transition">
                    <?php echo __('reset_password'); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
