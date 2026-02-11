<?php
/**
 * Venue Admin Dashboard
 * Shows all conferences for venue administrators
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    redirect('/login.php');
}

if (!isVenueAdmin()) {
    setFlashMessage('error', 'Access denied. Venue admin only.');
    redirect('/admin/dashboard.php');
}

$db = getDB();

// Get all conferences (for venue admin)
$stmt = $db->prepare("
    SELECT c.*, u.username as owner_name,
           (SELECT COUNT(*) FROM questions WHERE conference_id = c.id) as question_count,
           (SELECT COUNT(*) FROM participants WHERE conference_id = c.id) as participant_count,
           (SELECT COUNT(*) FROM guest_questions WHERE conference_id = c.id) as guest_question_count
    FROM conferences c
    JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
");
$stmt->execute();
$conferences = $stmt->fetchAll();

// Get statistics
$stats = [
    'total_conferences' => count($conferences),
    'active_conferences' => array_filter($conferences, fn($c) => $c['is_active']),
    'total_participants' => array_sum(array_column($conferences, 'participant_count')),
    'total_questions' => array_sum(array_column($conferences, 'question_count'))
];

$pageTitle = 'Venue Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold">🏢 Venue Admin Dashboard</h1>
                    <p class="text-indigo-200 mt-1">Manage all conferences and venue operations</p>
                </div>
                <div class="flex gap-3">
                    <a href="/admin/dashboard.php" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition">
                        My Conferences
                    </a>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php echo showFlashMessage(); ?>
        
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="text-4xl mb-2">📊</div>
                <div class="text-3xl font-bold"><?php echo $stats['total_conferences']; ?></div>
                <div class="text-gray-500">Total Conferences</div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="text-4xl mb-2">✅</div>
                <div class="text-3xl font-bold text-green-600"><?php echo count($stats['active_conferences']); ?></div>
                <div class="text-gray-500">Active</div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="text-4xl mb-2">👥</div>
                <div class="text-3xl font-bold text-blue-600"><?php echo $stats['total_participants']; ?></div>
                <div class="text-gray-500">Total Participants</div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="text-4xl mb-2">❓</div>
                <div class="text-3xl font-bold text-purple-600"><?php echo $stats['total_questions']; ?></div>
                <div class="text-gray-500">Total Questions</div>
            </div>
        </div>

        <!-- Conferences List -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-bold">All Conferences</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Participants</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Questions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($conferences as $conf): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?php echo e($conf['name']); ?></div>
                                    <div class="text-sm text-gray-500">Code: <?php echo e($conf['unique_code']); ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo e($conf['owner_name']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($conf['is_active']): ?>
                                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Active</span>
                                    <?php else: ?>
                                        <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php echo $conf['participant_count']; ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php echo $conf['question_count']; ?> regular<br>
                                    <span class="text-purple-600"><?php echo $conf['guest_question_count']; ?> guest</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <a href="combined-dashboard.php?conference_id=<?php echo $conf['id']; ?>" 
                                           class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded transition">
                                            Manage
                                        </a>
                                        <a href="files.php?conference_id=<?php echo $conf['id']; ?>" 
                                           class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1 rounded transition">
                                            Files
                                        </a>
                                        <a href="runofshow.php?conference_id=<?php echo $conf['id']; ?>" 
                                           class="bg-purple-600 hover:bg-purple-700 text-white text-xs px-3 py-1 rounded transition">
                                            Run of Show
                                        </a>
                                        <a href="/overlay/display.php?conference=<?php echo e($conf['uuid']); ?>" 
                                           target="_blank"
                                           class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1 rounded transition">
                                           Display
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
