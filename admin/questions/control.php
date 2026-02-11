<?php
/**
 * Question Control Panel
 */
$pageTitle = 'Control Panel';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$db = getDB();

$conferenceId = intval($_GET['conference_id'] ?? 0);
$questionId = intval($_GET['question_id'] ?? 0);
$action = $_GET['action'] ?? '';

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
        case 'activate':
            // Deactivate all other questions first
            $stmt = $db->prepare("UPDATE questions SET is_active = 0, show_results = 0 WHERE conference_id = ?");
            $stmt->execute([$conferenceId]);
            // Activate this one
            $stmt = $db->prepare("UPDATE questions SET is_active = 1 WHERE id = ? AND conference_id = ?");
            $stmt->execute([$questionId, $conferenceId]);
            setFlashMessage('success', __('question_activated'));
            break;
            
        case 'deactivate':
            $stmt = $db->prepare("UPDATE questions SET is_active = 0, show_results = 0 WHERE id = ? AND conference_id = ?");
            $stmt->execute([$questionId, $conferenceId]);
            setFlashMessage('success', __('question_deactivated'));
            break;
            
        case 'show_results':
            $stmt = $db->prepare("UPDATE questions SET show_results = 1 WHERE id = ? AND conference_id = ?");
            $stmt->execute([$questionId, $conferenceId]);
            setFlashMessage('success', __('results_visible'));
            break;
            
        case 'hide_results':
            $stmt = $db->prepare("UPDATE questions SET show_results = 0 WHERE id = ? AND conference_id = ?");
            $stmt->execute([$questionId, $conferenceId]);
            setFlashMessage('success', __('results_hidden'));
            break;
    }
    redirect('control.php?conference_id=' . $conferenceId);
}

// Get all questions
$stmt = $db->prepare("SELECT * FROM questions WHERE conference_id = ? ORDER BY created_at DESC");
$stmt->execute([$conferenceId]);
$questions = $stmt->fetchAll();

// Get active question
$stmt = $db->prepare("SELECT * FROM questions WHERE conference_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$conferenceId]);
$activeQuestion = $stmt->fetch();

// Get answers for active question
$answers = [];
if ($activeQuestion) {
    $stmt = $db->prepare("SELECT answer_text, COUNT(*) as count FROM answers WHERE question_id = ? GROUP BY answer_text");
    $stmt->execute([$activeQuestion['id']]);
    $answers = $stmt->fetchAll();
}
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800"><?php echo __('control_panel'); ?></h1>
    <p class="text-gray-600 mt-2"><?php echo __('conference'); ?>: <strong><?php echo e($conference['name']); ?></strong></p>
</div>

<?php showFlashMessage(); ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Questions List -->
    <div>
        <h2 class="text-xl font-bold text-gray-800 mb-4"><?php echo __('questions'); ?></h2>
        <div class="space-y-3">
            <?php foreach ($questions as $question): ?>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 <?php echo $question['is_active'] ? 'border-green-500' : 'border-gray-300'; ?>">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-gray-800"><?php echo e($question['question_text']); ?></p>
                            <p class="text-sm text-gray-500"><?php echo ucfirst($question['question_type']); ?></p>
                        </div>
                        <div class="flex gap-2">
                            <?php if ($question['is_active']): ?>
                                <a href="?conference_id=<?php echo $conferenceId; ?>&question_id=<?php echo $question['id']; ?>&action=deactivate" 
                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                                    <?php echo __('stop'); ?>
                                </a>
                                <?php if ($question['show_results']): ?>
                                    <a href="?conference_id=<?php echo $conferenceId; ?>&question_id=<?php echo $question['id']; ?>&action=hide_results" 
                                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">
                                        <?php echo __('hide_results'); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="?conference_id=<?php echo $conferenceId; ?>&question_id=<?php echo $question['id']; ?>&action=show_results" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                        <?php echo __('show_results'); ?>
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="?conference_id=<?php echo $conferenceId; ?>&question_id=<?php echo $question['id']; ?>&action=activate" 
                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                    <?php echo __('activate'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($question['is_active']): ?>
                        <div class="mt-2 flex gap-2">
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs"><?php echo strtoupper(__('active')); ?></span>
                            <?php if ($question['show_results']): ?>
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs"><?php echo strtoupper(__('showing_results')); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Preview -->
    <div>
        <h2 class="text-xl font-bold text-gray-800 mb-4"><?php echo __('live_preview'); ?></h2>
        <div class="bg-gray-900 rounded-lg p-6 text-white min-h-[400px]">
            <?php if ($activeQuestion): ?>
                <h3 class="text-xl font-bold mb-4"><?php echo e($activeQuestion['question_text']); ?></h3>
                
                <?php if ($activeQuestion['show_results'] && !empty($answers)): ?>
                    <div class="mt-4">
                        <h4 class="text-lg font-semibold mb-2"><?php echo __('results'); ?>:</h4>
                        <?php if ($activeQuestion['chart_type'] === 'wordcloud'): ?>
                            <div id="wordcloud" class="flex flex-wrap gap-2 justify-center">
                                <?php foreach ($answers as $answer): 
                                    $size = min(48, max(12, 12 + $answer['count'] * 4));
                                ?>
                                    <span style="font-size: <?php echo $size; ?>px;" class="text-blue-400">
                                        <?php echo e($answer['answer_text']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($answers as $answer): 
                                    $maxCount = max(array_column($answers, 'count'));
                                    $percentage = $maxCount > 0 ? ($answer['count'] / $maxCount) * 100 : 0;
                                ?>
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span><?php echo e($answer['answer_text']); ?></span>
                                            <span><?php echo $answer['count']; ?> <?php echo __('votes'); ?></span>
                                        </div>
                                        <div class="bg-gray-700 rounded-full h-4">
                                            <div class="bg-blue-500 h-4 rounded-full transition-all duration-500" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-400"><?php echo __('waiting_for_participants'); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center text-gray-500 mt-20">
                    <p class="text-xl"><?php echo __('no_active_question'); ?></p>
                    <p class="mt-2"><?php echo __('activate_question_preview'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="mt-8">
    <a href="list.php?conference_id=<?php echo $conferenceId; ?>" class="text-purple-600 hover:text-purple-800 font-medium">&larr; <?php echo __('back_to_questions'); ?></a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
