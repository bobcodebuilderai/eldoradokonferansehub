<?php
/**
 * Admin Dashboard - Conference List
 */
$pageTitle = 'Dashboard';

// Prevent caching issues when returning from overlay
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');

require_once __DIR__ . '/../includes/header.php';
requireLogin();

// Regenerate CSRF token if needed to prevent session conflicts
if (!isset($_SESSION['csrf_token'])) {
    generateCSRFToken();
}

$db = getDB();
$user = getCurrentUser();

// Get all conferences for this user
$stmt = $db->prepare("SELECT * FROM conferences WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$conferences = $stmt->fetchAll();
?>

<div class="mb-8">
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo __('conferences'); ?></h1>
        <a href="conferences/create.php" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg font-medium transition">
            + <?php echo __('create_conference'); ?>
        </a>
    </div>
    <p class="text-gray-600 mt-2"><?php echo __('manage_interactive_conferences'); ?></p>
</div>

<?php showFlashMessage(); ?>

<?php if (empty($conferences)): ?>
    <div class="bg-white rounded-lg shadow p-12 text-center">
        <div class="text-6xl mb-4">📅</div>
        <h2 class="text-2xl font-semibold text-gray-700 mb-2"><?php echo __('no_conferences_yet'); ?></h2>
        <p class="text-gray-600 mb-6"><?php echo __('create_your_first_conference'); ?></p>
        <a href="conferences/create.php" class="gradient-bg text-white px-8 py-3 rounded-lg font-medium inline-block hover:opacity-90 transition">
            <?php echo __('create_first_conference'); ?>
        </a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($conferences as $conference): 
            $guestUrl = getBaseUrl() . '/../guest/?code=' . urlencode($conference['unique_code']);
        ?>
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6 border-l-4 <?php echo $conference['is_active'] ? 'border-green-500' : 'border-gray-400'; ?>">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-xl font-bold text-gray-800"><?php echo e($conference['name']); ?></h3>
                    <span class="px-2 py-1 text-xs rounded-full <?php echo $conference['is_active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                        <?php echo $conference['is_active'] ? __('active') : __('inactive'); ?>
                    </span>
                </div>
                
                <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                    <?php echo e($conference['description'] ?: __('no_description')); ?>
                </p>
                
                <div class="text-sm text-gray-500 mb-4">
                    <div><strong><?php echo __('conference_code'); ?>:</strong> <code class="bg-gray-100 px-2 py-1 rounded"><?php echo e($conference['unique_code']); ?></code></div>
                    <div><strong><?php echo __('created'); ?>:</strong> <?php echo date('M j, Y', strtotime($conference['created_at'])); ?></div>
                </div>
                
                <div class="flex flex-wrap gap-2">
                    <a href="combined-dashboard.php?conference_id=<?php echo $conference['id']; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-medium transition" title="Kombinert dashboard">
                        📊 Kombinert
                    </a>
                    <a href="questions/list.php?conference_id=<?php echo $conference['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium transition">
                        <?php echo __('questions'); ?>
                    </a>
                    <a href="guest-questions/moderate.php?conference_id=<?php echo $conference['id']; ?>" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded text-sm font-medium transition">
                        <?php echo __('moderate'); ?>
                    </a>
                    <a href="conferences/edit.php?id=<?php echo $conference['id']; ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded text-sm font-medium transition">
                        <?php echo __('edit'); ?>
                    </a>
                    <a href="../overlay/display.php?conference=<?php echo e($conference['uuid'] ?? ''); ?>" target="_blank" rel="noopener noreferrer" class="bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded text-sm font-medium transition">
                        <?php echo __('overlay'); ?>
                    </a>
                </div>
                
                <!-- QR Code Download -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600"><?php echo __('qr_code'); ?></span>
                        <a href="../api/qr.php?code=<?php echo e($conference['unique_code']); ?>" target="_blank" class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                            <?php echo __('download_qr'); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Memory management for dashboard
(function() {
    'use strict';
    
    // Track intervals and timeouts for cleanup
    window.dashboardIntervals = window.dashboardIntervals || [];
    
    // AbortController for canceling pending requests
    let dashboardController = null;
    
    // Clear any existing intervals when leaving page
    window.addEventListener('beforeunload', function() {
        if (window.dashboardIntervals) {
            window.dashboardIntervals.forEach(function(id) {
                clearInterval(id);
            });
            window.dashboardIntervals = [];
        }
        if (window.dashboardTimeout) {
            clearTimeout(window.dashboardTimeout);
        }
        // Cancel any pending requests
        if (dashboardController) {
            dashboardController.abort();
            dashboardController = null;
        }
    });
    
    // Limit concurrent requests
    let isLoading = false;
    
    // Helper to safely make requests with AbortController
    window.safeFetch = function(url, options) {
        if (isLoading) return Promise.reject('Request already in progress');
        isLoading = true;
        
        // Cancel any existing request
        if (dashboardController) {
            dashboardController.abort();
        }
        dashboardController = new AbortController();
        
        return fetch(url, {
            ...options,
            signal: dashboardController.signal
        })
            .finally(function() {
                isLoading = false;
                dashboardController = null;
            });
    };
    
    // Cleanup event listeners on unload
    window.addEventListener('beforeunload', function() {
        // Remove any dynamically added listeners
        document.querySelectorAll('[data-dynamic-listener]').forEach(function(el) {
            el.remove();
        });
    });
})();
</script>
