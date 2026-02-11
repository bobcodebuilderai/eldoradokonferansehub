<?php
/**
 * List Questions
 */
$pageTitle = 'Questions';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$db = getDB();

$conferenceId = intval($_GET['conference_id'] ?? 0);

// Verify conference ownership
$stmt = $db->prepare("SELECT id, name, unique_code FROM conferences WHERE id = ? AND user_id = ?");
$stmt->execute([$conferenceId, $_SESSION['user_id']]);
$conference = $stmt->fetch();

if (!$conference) {
    setFlashMessage('error', 'Conference not found.');
    redirect('../dashboard.php');
}

// Get all questions for this conference
$stmt = $db->prepare("SELECT * FROM questions WHERE conference_id = ? ORDER BY created_at DESC");
$stmt->execute([$conferenceId]);
$questions = $stmt->fetchAll();

// Get active question
$stmt = $db->prepare("SELECT * FROM questions WHERE conference_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$conferenceId]);
$activeQuestion = $stmt->fetch();
?>

<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?php echo __('questions'); ?></h1>
            <p class="text-gray-600 mt-2"><?php echo __('for'); ?>: <strong><?php echo e($conference['name']); ?></strong></p>
        </div>
        <div class="space-x-2">
            <a href="control.php?conference_id=<?php echo $conferenceId; ?>" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg font-medium transition">
                <?php echo __('control_panel'); ?>
            </a>
            <a href="create.php?conference_id=<?php echo $conferenceId; ?>" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg font-medium transition">
                + <?php echo __('add_question'); ?>
            </a>
        </div>
    </div>
</div>

<?php showFlashMessage(); ?>

<?php if ($activeQuestion): ?>
    <div class="bg-green-50 border border-green-400 rounded-lg p-4 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-green-800 font-semibold"><?php echo __('active'); ?>:</span>
                <span class="text-green-900 ml-2"><?php echo e($activeQuestion['question_text']); ?></span>
            </div>
            <span class="bg-green-500 text-white px-3 py-1 rounded-full text-sm"><?php echo __('live'); ?></span>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($questions)): ?>
    <div class="bg-white rounded-lg shadow p-12 text-center">
        <div class="text-6xl mb-4">❓</div>
        <h2 class="text-2xl font-semibold text-gray-700 mb-2"><?php echo __('no_questions_yet'); ?></h2>
        <p class="text-gray-600 mb-6"><?php echo __('create_first_question'); ?></p>
        <a href="create.php?conference_id=<?php echo $conferenceId; ?>" class="gradient-bg text-white px-8 py-3 rounded-lg font-medium inline-block hover:opacity-90 transition">
            <?php echo __('create_first_question'); ?>
        </a>
    </div>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($questions as $question): ?>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 <?php echo $question['is_active'] ? 'border-green-500' : 'border-gray-300'; ?>">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-800"><?php echo e($question['question_text']); ?></h3>
                        <div class="flex gap-4 mt-2 text-sm text-gray-600">
                            <span class="bg-gray-100 px-2 py-1 rounded"><?php echo __('type'); ?>: <?php echo ucfirst($question['question_type']); ?></span>
                            <span class="bg-gray-100 px-2 py-1 rounded"><?php echo __('chart'); ?>: <?php echo str_replace('_', ' ', ucfirst($question['chart_type'])); ?></span>
                            <?php if ($question['is_active']): ?>
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded"><?php echo __('active'); ?></span>
                            <?php endif; ?>
                            <?php if ($question['show_results']): ?>
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded"><?php echo __('showing_results'); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($question['options'])): 
                            $options = json_decode($question['options'], true);
                            if ($options): ?>
                            <div class="mt-3">
                                <span class="text-sm text-gray-500"><?php echo __('options'); ?>:</span>
                                <div class="flex flex-wrap gap-2 mt-1">
                                    <?php foreach ($options as $opt): ?>
                                        <span class="bg-gray-100 px-2 py-1 rounded text-sm"><?php echo e($opt); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; endif; ?>
                    </div>
                    
                    <div class="flex flex-col gap-2 ml-4">
                        <a href="control.php?conference_id=<?php echo $conferenceId; ?>&question_id=<?php echo $question['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium text-center transition">
                            <?php echo __('control_panel'); ?>
                        </a>
                        <a href="delete.php?id=<?php echo $question['id']; ?>&conference_id=<?php echo $conferenceId; ?>" onclick="return confirm('<?php echo __('confirm_delete'); ?>');" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded text-sm font-medium text-center transition">
                            <?php echo __('delete'); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mt-8">
    <a href="../dashboard.php" class="text-purple-600 hover:text-purple-800 font-medium">&larr; <?php echo __('back_to_dashboard'); ?></a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
