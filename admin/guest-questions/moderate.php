<?php
/**
 * Guest Questions Moderation
 */
$pageTitle = 'Moderate Questions';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$db = getDB();

$conferenceId = intval($_GET['conference_id'] ?? 0);
$action = $_GET['action'] ?? '';
$questionId = intval($_GET['id'] ?? 0);

// Verify conference ownership
$stmt = $db->prepare("SELECT id, name FROM conferences WHERE id = ? AND user_id = ?");
$stmt->execute([$conferenceId, $_SESSION['user_id']]);
$conference = $stmt->fetch();

if (!$conference) {
    setFlashMessage('error', 'Conference not found.');
    redirect('../dashboard.php');
}

// Handle actions
if ($action && $questionId) {
    switch ($action) {
        case 'approve':
            $stmt = $db->prepare("UPDATE guest_questions SET status = 'approved' WHERE id = ? AND conference_id = ?");
            $stmt->execute([$questionId, $conferenceId]);
            setFlashMessage('success', __('approved'));
            break;
            
        case 'reject':
            $stmt = $db->prepare("UPDATE guest_questions SET status = 'rejected' WHERE id = ? AND conference_id = ?");
            $stmt->execute([$questionId, $conferenceId]);
            setFlashMessage('success', __('rejected'));
            break;
            
        case 'display':
            // First, remove any currently displayed question
            $stmt = $db->prepare("UPDATE guest_questions SET status = 'approved' WHERE status = 'displayed' AND conference_id = ?");
            $stmt->execute([$conferenceId]);
            // Then display this one
            $stmt = $db->prepare("UPDATE guest_questions SET status = 'displayed' WHERE id = ? AND conference_id = ?");
            $stmt->execute([$questionId, $conferenceId]);
            setFlashMessage('success', __('displayed'));
            break;
            
        case 'remove':
            $stmt = $db->prepare("UPDATE guest_questions SET status = 'approved' WHERE id = ? AND conference_id = ?");
            $stmt->execute([$questionId, $conferenceId]);
            setFlashMessage('success', __('remove_from_screen'));
            break;
    }
    redirect('moderate.php?conference_id=' . $conferenceId);
}

// Get all guest questions
$stmt = $db->prepare("
    SELECT gq.*, p.name as participant_name 
    FROM guest_questions gq 
    JOIN participants p ON gq.participant_id = p.id 
    WHERE gq.conference_id = ? 
    ORDER BY FIELD(gq.status, 'displayed', 'pending', 'approved', 'rejected'), gq.created_at DESC
");
$stmt->execute([$conferenceId]);
$questions = $stmt->fetchAll();

// Group by status
$pending = array_filter($questions, fn($q) => $q['status'] === 'pending');
$displayed = array_filter($questions, fn($q) => $q['status'] === 'displayed');
$approved = array_filter($questions, fn($q) => $q['status'] === 'approved');
$rejected = array_filter($questions, fn($q) => $q['status'] === 'rejected');

// Handle AJAX polling request
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'pending_count' => count($pending),
        'approved_count' => count($approved),
        'rejected_count' => count($rejected),
        'displayed_count' => count($displayed)
    ]);
    exit;
}
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800"><?php echo __('moderate_guest_questions'); ?></h1>
    <p class="text-gray-600 mt-2"><?php echo __('conference'); ?>: <strong><?php echo e($conference['name']); ?></strong></p>
</div>

<?php showFlashMessage(); ?>

<!-- Auto-refresh indicator -->
<div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-6 flex justify-between items-center">
    <span class="text-blue-800 text-sm"><?php echo __('page_auto_refreshes'); ?></span>
    <button onclick="location.reload()" class="text-blue-600 hover:text-blue-800 text-sm font-medium"><?php echo __('refresh_now'); ?></button>
</div>

<!-- Question Sections Container -->
<div id="question-sections">
    <!-- Currently Displayed -->
    <?php if (!empty($displayed)): ?>
        <div class="mb-8">
            <h2 class="text-xl font-bold text-green-700 mb-4">🖥️ <?php echo __('currently_displayed'); ?></h2>
            <div class="space-y-3">
                <?php foreach ($displayed as $q): ?>
                    <div class="bg-green-50 border-2 border-green-500 rounded-lg p-4">
                        <p class="text-lg font-medium text-gray-800"><?php echo e($q['question_text']); ?></p>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo __('from'); ?>: <?php echo $q['is_anonymous'] ? __('anonymous') : e($q['participant_name']); ?> | 
                            <?php echo date('M j, H:i', strtotime($q['created_at'])); ?>
                        </p>
                        <div class="mt-3 flex gap-2">
                            <a href="?conference_id=<?php echo $conferenceId; ?>&id=<?php echo $q['id']; ?>&action=remove" 
                                class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded text-sm font-medium">
                                <?php echo __('remove_from_screen'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pending -->
    <div class="mb-8">
        <h2 class="text-xl font-bold text-yellow-700 mb-4">⏳ <?php echo __('pending_approval'); ?> (<span id="pending-count"><?php echo count($pending); ?></span>)</h2>
        <?php if (empty($pending)): ?>
            <p class="text-gray-500 italic" id="no-pending-msg"><?php echo __('no_pending_questions'); ?></p>
        <?php else: ?>
            <div class="space-y-3" id="pending-list">
                <?php foreach ($pending as $q): ?>
                    <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4">
                        <p class="text-gray-800"><?php echo e($q['question_text']); ?></p>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo __('from'); ?>: <?php echo $q['is_anonymous'] ? __('anonymous') : e($q['participant_name']); ?> | 
                            <?php echo date('M j, H:i', strtotime($q['created_at'])); ?>
                        </p>
                        <div class="mt-3 flex gap-2">
                            <a href="?conference_id=<?php echo $conferenceId; ?>&id=<?php echo $q['id']; ?>&action=approve" 
                                class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm font-medium">
                                <?php echo __('approve'); ?>
                            </a>
                            <a href="?conference_id=<?php echo $conferenceId; ?>&id=<?php echo $q['id']; ?>&action=reject" 
                                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded text-sm font-medium">
                                <?php echo __('reject'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Approved -->
    <div class="mb-8">
        <h2 class="text-xl font-bold text-blue-700 mb-4">✅ <?php echo __('approved'); ?> (<span id="approved-count"><?php echo count($approved); ?></span>)</h2>
        <?php if (empty($approved)): ?>
            <p class="text-gray-500 italic" id="no-approved-msg"><?php echo __('no_approved_questions'); ?></p>
        <?php else: ?>
            <div class="space-y-3" id="approved-list">
                <?php foreach ($approved as $q): ?>
                    <div class="bg-white border border-gray-300 rounded-lg p-4">
                        <p class="text-gray-800"><?php echo e($q['question_text']); ?></p>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo __('from'); ?>: <?php echo $q['is_anonymous'] ? __('anonymous') : e($q['participant_name']); ?> | 
                            <?php echo date('M j, H:i', strtotime($q['created_at'])); ?>
                        </p>
                        <div class="mt-3 flex gap-2">
                            <a href="?conference_id=<?php echo $conferenceId; ?>&id=<?php echo $q['id']; ?>&action=display" 
                                class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm font-medium">
                                <?php echo __('show_on_screen'); ?>
                            </a>
                            <a href="?conference_id=<?php echo $conferenceId; ?>&id=<?php echo $q['id']; ?>&action=reject" 
                                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded text-sm font-medium">
                                <?php echo __('reject'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Rejected -->
    <div class="mb-8">
        <h2 class="text-xl font-bold text-red-700 mb-4">❌ <?php echo __('rejected'); ?> (<span id="rejected-count"><?php echo count($rejected); ?></span>)</h2>
        <?php if (empty($rejected)): ?>
            <p class="text-gray-500 italic" id="no-rejected-msg"><?php echo __('no_rejected_questions'); ?></p>
        <?php else: ?>
            <div class="space-y-3" id="rejected-list">
                <?php foreach ($rejected as $q): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 opacity-75">
                        <p class="text-gray-800"><?php echo e($q['question_text']); ?></p>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php echo __('from'); ?>: <?php echo $q['is_anonymous'] ? __('anonymous') : e($q['participant_name']); ?> | 
                            <?php echo date('M j, H:i', strtotime($q['created_at'])); ?>
                        </p>
                        <div class="mt-3 flex gap-2">
                            <a href="?conference_id=<?php echo $conferenceId; ?>&id=<?php echo $q['id']; ?>&action=approve" 
                                class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm font-medium">
                                <?php echo __('approve'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// AJAX polling for new questions (every 5 seconds)
let lastPendingCount = <?php echo count($pending); ?>;
let lastApprovedCount = <?php echo count($approved); ?>;
let lastRejectedCount = <?php echo count($rejected); ?>;
let pollInterval;

function pollQuestions() {
    fetch('?conference_id=<?php echo $conferenceId; ?>&ajax=1')
        .then(r => r.json())
        .then(data => {
            // Update counts
            document.getElementById('pending-count').textContent = data.pending_count;
            document.getElementById('approved-count').textContent = data.approved_count;
            document.getElementById('rejected-count').textContent = data.rejected_count;
            
            // Check if counts changed
            const hasChanges = data.pending_count !== lastPendingCount ||
                             data.approved_count !== lastApprovedCount ||
                             data.rejected_count !== lastRejectedCount;
            
            if (hasChanges) {
                // Update last known counts
                lastPendingCount = data.pending_count;
                lastApprovedCount = data.approved_count;
                lastRejectedCount = data.rejected_count;
                
                // Reload to show new content
                location.reload();
            }
        })
        .catch(err => console.log('Poll error:', err));
}

// Start polling every 5 seconds
pollInterval = setInterval(pollQuestions, 5000);

// Clean up on page unload
window.addEventListener('beforeunload', () => {
    clearInterval(pollInterval);
});
</script>

<div class="mt-8">
    <a href="../dashboard.php" class="text-purple-600 hover:text-purple-800 font-medium">&larr; <?php echo __('back_to_dashboard'); ?></a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
