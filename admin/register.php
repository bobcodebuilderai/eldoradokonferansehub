<?php
/**
 * Admin Registration Page
 */
$pageTitle = 'Register';
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

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = __('csrf_error');
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($username) || strlen($username) < 3) {
            $errors[] = __('username_at_least_3_chars');
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('invalid_email_address');
        }
        
        if (empty($password) || strlen($password) < 8) {
            $errors[] = __('password_at_least_8_chars');
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = __('passwords_dont_match');
        }
        
        if (empty($errors)) {
            $result = registerUser($username, $email, $password);
            if ($result['success']) {
                setFlashMessage('success', __('registration_successful') . ' ' . __('please_log_in'));
                redirect('index.php');
            } else {
                $errors[] = $result['message'];
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
    <title><?php echo __('register'); ?> - <?php echo __('app_name'); ?></title>
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
            <h1 class="text-3xl font-bold text-gray-800"><?php echo __('create_account'); ?></h1>
            <p class="text-gray-600 mt-2"><?php echo __('register'); ?> - <?php echo __('app_name'); ?></p>
            
            <!-- Language Switcher -->
            <div class="flex justify-center items-center space-x-2 mt-4">
                <a href="?lang=no" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'no' ? 'bg-purple-600 text-white font-bold' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">Norsk</a>
                <a href="?lang=en" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'en' ? 'bg-purple-600 text-white font-bold' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">English</a>
            </div>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo e($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('username'); ?></label>
                <input type="text" id="username" name="username" required 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    placeholder="<?php echo __('username'); ?>">
            </div>
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('email'); ?></label>
                <input type="email" id="email" name="email" required 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    placeholder="<?php echo __('email'); ?>">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1"><?php echo __('password'); ?></label>
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
                class="w-full gradient-bg text-white font-semibold py-3 rounded-lg hover:opacity-90 transition mt-6">
                <?php echo __('register'); ?>
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <div class="text-gray-600 text-sm">
                <?php echo __('already_have_account'); ?> <a href="index.php" class="text-purple-600 hover:text-purple-800 font-medium"><?php echo __('login'); ?></a>
            </div>
        </div>
    </div>
</body>
</html>
