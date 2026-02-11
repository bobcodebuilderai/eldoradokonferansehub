<?php
/**
 * Create Question
 */
$pageTitle = 'Create Question';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$db = getDB();
$errors = [];

$conferenceId = intval($_GET['conference_id'] ?? 0);

// Verify conference ownership
$stmt = $db->prepare("SELECT id, name FROM conferences WHERE id = ? AND user_id = ?");
$stmt->execute([$conferenceId, $_SESSION['user_id']]);
$conference = $stmt->fetch();

if (!$conference) {
    setFlashMessage('error', 'Conference not found.');
    redirect('../dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = __('csrf_error');
    } else {
        $questionText = trim($_POST['question_text'] ?? '');
        $questionType = $_POST['question_type'] ?? 'single';
        $chartType = $_POST['chart_type'] ?? 'pie';
        $options = [];
        
        if ($questionType === 'single' || $questionType === 'multiple') {
            $optionTexts = $_POST['options'] ?? [];
            foreach ($optionTexts as $opt) {
                $opt = trim($opt);
                if (!empty($opt)) {
                    $options[] = $opt;
                }
            }
            if (count($options) < 2) {
                $errors[] = __('at_least_2_options');
            }
        }
        
        if (empty($questionText)) {
            $errors[] = __('question_text_required');
        }
        
        if (empty($errors)) {
            $optionsJson = !empty($options) ? json_encode($options) : null;
            
            $stmt = $db->prepare("INSERT INTO questions (conference_id, question_text, question_type, options, chart_type) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$conferenceId, $questionText, $questionType, $optionsJson, $chartType])) {
                setFlashMessage('success', __('question_created'));
                redirect('list.php?conference_id=' . $conferenceId);
            } else {
                $errors[] = __('failed_to_create_question');
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800"><?php echo __('create_question'); ?></h1>
    <p class="text-gray-600 mt-2"><?php echo __('for'); ?>: <strong><?php echo e($conference['name']); ?></strong></p>
</div>

<?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="" id="questionForm">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        
        <div class="mb-6">
            <label for="question_text" class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('question_text'); ?> *</label>
            <textarea id="question_text" name="question_text" rows="3" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                placeholder="<?php echo __('question_text'); ?>"></textarea>
        </div>
        
        <div class="mb-6">
            <label for="question_type" class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('question_type'); ?> *</label>
            <select id="question_type" name="question_type" required onchange="toggleOptions()"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="single"><?php echo __('single_choice'); ?></option>
                <option value="multiple"><?php echo __('multiple_choice'); ?></option>
                <option value="rating"><?php echo __('rating'); ?> (1-10)</option>
                <option value="wordcloud"><?php echo __('wordcloud'); ?></option>
            </select>
        </div>
        
        <div id="optionsSection" class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('options'); ?></label>
            <div id="optionsContainer" class="space-y-2">
                <input type="text" name="options[]" placeholder="<?php echo __('options'); ?> 1"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <input type="text" name="options[]" placeholder="<?php echo __('options'); ?> 2"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <button type="button" onclick="addOption()" class="mt-2 text-purple-600 hover:text-purple-800 text-sm font-medium">
                + <?php echo __('add_option'); ?>
            </button>
        </div>
        
        <div class="mb-6">
            <label for="chart_type" class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('chart_type'); ?></label>
            <select id="chart_type" name="chart_type" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="pie"><?php echo __('pie_chart'); ?></option>
                <option value="bar_horizontal"><?php echo __('bar_horizontal'); ?></option>
                <option value="bar_vertical"><?php echo __('bar_vertical'); ?></option>
                <option value="wordcloud"><?php echo __('wordcloud'); ?></option>
            </select>
        </div>
        
        <div class="flex gap-4">
            <button type="submit" class="gradient-bg text-white px-6 py-2 rounded-lg font-medium hover:opacity-90 transition">
                <?php echo __('create_question'); ?>
            </button>
            <a href="list.php?conference_id=<?php echo $conferenceId; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium transition">
                <?php echo __('cancel'); ?>
            </a>
        </div>
    </form>
</div>

<script>
function toggleOptions() {
    const type = document.getElementById('question_type').value;
    const optionsSection = document.getElementById('optionsSection');
    
    if (type === 'single' || type === 'multiple') {
        optionsSection.style.display = 'block';
    } else {
        optionsSection.style.display = 'none';
    }
}

function addOption() {
    const container = document.getElementById('optionsContainer');
    const count = container.children.length + 1;
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'options[]';
    input.placeholder = '<?php echo __('options'); ?> ' + count;
    input.className = 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent mt-2';
    container.appendChild(input);
}

// Initialize
toggleOptions();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
