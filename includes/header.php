<?php
/**
 * Admin Header Template
 */
require_once __DIR__ . '/auth.php';

// Handle language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], ['no', 'en'])) {
    setLanguage($_GET['lang']);
    // Remove lang parameter from URL
    $currentUrl = strtok($_SERVER['REQUEST_URI'], '?');
    if (!empty($_GET) && count($_GET) > 1) {
        $params = $_GET;
        unset($params['lang']);
        $currentUrl .= '?' . http_build_query($params);
    }
    redirect($currentUrl);
}

$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?><?php echo __('app_name'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php if (isLoggedIn()): ?>
    <nav class="gradient-bg text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="dashboard.php" class="font-bold text-xl"><?php echo __('app_name'); ?></a>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Navigation Links -->
                    <div class="hidden md:flex items-center space-x-2 mr-4">
                        <a href="dashboard.php" class="px-3 py-1 rounded text-sm text-white hover:bg-white/20 transition"><?php echo __('dashboard'); ?></a>
                        <?php if (isVenueAdmin()): ?>
                            <a href="venue-admin.php" class="px-3 py-1 rounded text-sm bg-indigo-500 text-white font-medium hover:bg-indigo-600 transition">🏢 Venue Admin</a>
                        <?php endif; ?>
                        <?php if (isset($_GET['conference_id'])): ?>
                            <a href="combined-dashboard.php?conference_id=<?php echo intval($_GET['conference_id']); ?>" class="px-3 py-1 rounded text-sm bg-white/20 text-white font-medium hover:bg-white/30 transition">Kombinert dashboard</a>
                        <?php endif; ?>
                    </div>
                    <!-- Language Switcher -->
                    <div class="flex items-center space-x-2 mr-4">
                        <a href="?lang=no" class="px-2 py-1 rounded text-sm <?php echo $currentLang === 'no' ? 'bg-white text-purple-600 font-bold' : 'text-white hover:bg-white/20'; ?>">NO</a>
                        <a href="?lang=en" class="px-2 py-1 rounded text-sm <?php echo $currentLang === 'en' ? 'bg-white text-purple-600 font-bold' : 'text-white hover:bg-white/20'; ?>">EN</a>
                    </div>
                    <span class="text-sm"><?php echo __('welcome'); ?>, <?php echo e($_SESSION['username']); ?></span>
                    <a href="profile.php" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded text-sm font-medium transition">👤 Profile</a>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-sm font-medium transition"><?php echo __('logout'); ?></a>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
