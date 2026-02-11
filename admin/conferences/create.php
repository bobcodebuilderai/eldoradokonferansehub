<?php
/**
 * Create Conference
 */
$pageTitle = 'Create Conference';
require_once __DIR__ . '/../../includes/header.php';
requireLogin();

$db = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = __('csrf_error');
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $requireEmail = isset($_POST['require_email']) ? 1 : 0;
        $requireJobTitle = isset($_POST['require_job_title']) ? 1 : 0;
        $overlayBackground = $_POST['overlay_background'] ?? 'graphic';
        $screenWidth = intval($_POST['screen_width'] ?? 1920);
        $screenHeight = intval($_POST['screen_height'] ?? 1080);
        $language = $_POST['language'] ?? 'no';
        
        if (empty($name)) {
            $errors[] = __('conference_name_required');
        }
        
        if (empty($errors)) {
            // Generate unique code
            $uniqueCode = generateUniqueCode();
            
            // Keep generating until we get a unique one
            $stmt = $db->prepare("SELECT id FROM conferences WHERE unique_code = ?");
            while (true) {
                $stmt->execute([$uniqueCode]);
                if (!$stmt->fetch()) {
                    break;
                }
                $uniqueCode = generateUniqueCode();
            }
            
            $stmt = $db->prepare("INSERT INTO conferences (user_id, name, description, unique_code, require_email, require_job_title, overlay_background, screen_width, screen_height, language) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$_SESSION['user_id'], $name, $description, $uniqueCode, $requireEmail, $requireJobTitle, $overlayBackground, $screenWidth, $screenHeight, $language])) {
                $conferenceId = $db->lastInsertId();
                setFlashMessage('success', __('conference_created'));
                redirect('../dashboard.php');
            } else {
                $errors[] = __('failed_to_create_conference');
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800"><?php echo __('create_conference'); ?></h1>
    <p class="text-gray-600 mt-2"><?php echo __('set_up_new_conference'); ?></p>
</div>

<?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
        <?php foreach ($errors as $error): ?>
            <div><?php echo e($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        
        <div class="mb-6">
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('conference_name'); ?> *</label>
            <input type="text" id="name" name="name" required 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                placeholder="<?php echo __('conference_name'); ?>">
        </div>
        
        <div class="mb-6">
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('description'); ?></label>
            <textarea id="description" name="description" rows="3"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                placeholder="<?php echo __('description'); ?>"></textarea>
        </div>
        
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('registration_requirements'); ?></label>
            <div class="space-y-2">
                <label class="flex items-center">
                    <input type="checkbox" name="require_email" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700"><?php echo __('require_email_from_participants'); ?></span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="require_job_title" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700"><?php echo __('require_job_title_from_participants'); ?></span>
                </label>
            </div>
            <p class="text-sm text-gray-500 mt-2"><?php echo __('note_name_always_required'); ?></p>
        </div>
        
        <div class="mb-6">
            <label for="overlay_background" class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('overlay_background'); ?></label>
            <select id="overlay_background" name="overlay_background" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="graphic"><?php echo __('graphic'); ?></option>
                <option value="transparent"><?php echo __('transparent'); ?></option>
            </select>
            <p class="text-sm text-gray-500 mt-2"><?php echo __('graphic'); ?> = <?php echo $currentLang === 'no' ? 'Mørk gradient bakgrunn' : 'Dark gradient background'; ?>, <?php echo __('transparent'); ?> = <?php echo $currentLang === 'no' ? 'Gjennomsiktig for chroma key' : 'Transparent for chroma key'; ?></p>
        </div>
        
        <div class="mb-6">
            <label for="language" class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('language'); ?></label>
            <select id="language" name="language" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="no"><?php echo __('norwegian'); ?></option>
                <option value="en"><?php echo __('english'); ?></option>
            </select>
        </div>
        
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2"><?php echo __('display_resolution'); ?></label>
            <div class="flex gap-4">
                <div class="flex-1">
                    <label for="screen_width" class="block text-xs text-gray-500 mb-1"><?php echo __('screen_width'); ?> (px)</label>
                    <input type="number" id="screen_width" name="screen_width" value="1920" min="800" max="7680" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div class="flex-1">
                    <label for="screen_height" class="block text-xs text-gray-500 mb-1"><?php echo __('screen_height'); ?> (px)</label>
                    <input type="number" id="screen_height" name="screen_height" value="1080" min="600" max="4320" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-2"><?php echo $currentLang === 'no' ? 'Standard: 1920x1080 (Full HD). Bruk 3840x2160 for 4K.' : 'Default: 1920x1080 (Full HD). Use 3840x2160 for 4K.'; ?></p>
        </div>
        
        <div class="flex gap-4">
            <button type="submit" class="gradient-bg text-white px-6 py-2 rounded-lg font-medium hover:opacity-90 transition">
                <?php echo __('create_conference'); ?>
            </button>
            <a href="../dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium transition">
                <?php echo __('cancel'); ?>
            </a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
