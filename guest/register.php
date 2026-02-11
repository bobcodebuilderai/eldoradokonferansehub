<?php
/**
 * Guest Registration Page
 */
require_once __DIR__ . '/../includes/functions.php';

// Check if we have conference in session
if (empty($_SESSION['conference_id'])) {
    redirect('index.php');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM conferences WHERE id = ? AND is_active = 1");
$stmt->execute([$_SESSION['conference_id']]);
$conference = $stmt->fetch();

if (!$conference) {
    unset($_SESSION['conference_id']);
    unset($_SESSION['conference_code']);
    redirect('index.php');
}

$currentLang = getCurrentLanguage();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = __('csrf_error');
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $jobTitle = trim($_POST['job_title'] ?? '');
        
        // Validation
        if (empty($name)) {
            $errors[] = __('name') . ' ' . __('required_field');
        }
        
        if ($conference['require_email'] && empty($email)) {
            $errors[] = __('email') . ' ' . __('required_field');
        }
        
        if ($conference['require_job_title'] && empty($jobTitle)) {
            $errors[] = __('job_title') . ' ' . __('required_field');
        }
        
        if (empty($errors)) {
            // Check if participant already exists
            $stmt = $db->prepare("SELECT id FROM participants WHERE conference_id = ? AND name = ? AND (email = ? OR (? = '' AND email IS NULL))");
            $stmt->execute([$conference['id'], $name, $email, $email]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Re-login existing participant
                $_SESSION['participant_id'] = $existing['id'];
            } else {
                // Create new participant
                $stmt = $db->prepare("INSERT INTO participants (conference_id, name, email, job_title) VALUES (?, ?, ?, ?)");
                $stmt->execute([$conference['id'], $name, $email ?: null, $jobTitle ?: null]);
                $_SESSION['participant_id'] = $db->lastInsertId();
            }
            
            redirect('participant.php');
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo __('register'); ?> - <?php echo e($conference['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #0f172a;
            color: #f1f5f9;
        }
        .card {
            background-color: #1e293b;
            border: 1px solid #334155;
        }
        .input-field {
            background-color: #334155;
            border: 1px solid #475569;
            color: #f1f5f9;
        }
        .input-field:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
        .input-field::placeholder {
            color: #64748b;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body class="min-h-screen p-4">
    <div class="max-w-sm mx-auto py-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold"><?php echo e($conference['name']); ?></h1>
            <?php if ($conference['description']): ?>
                <p class="text-slate-400 mt-2 text-sm"><?php echo e($conference['description']); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 px-4 py-3 rounded-lg mb-6">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo e($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="card rounded-2xl p-6 shadow-xl">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-slate-300 mb-2"><?php echo __('name'); ?> *</label>
                <input type="text" id="name" name="name" required 
                    class="input-field w-full px-4 py-3 rounded-lg"
                    placeholder="<?php echo __('enter_full_name'); ?>">
            </div>
            
            <?php if ($conference['require_email']): ?>
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-slate-300 mb-2"><?php echo __('email'); ?> *</label>
                <input type="email" id="email" name="email" required 
                    class="input-field w-full px-4 py-3 rounded-lg"
                    placeholder="<?php echo __('email'); ?>">
            </div>
            <?php endif; ?>
            
            <?php if ($conference['require_job_title']): ?>
            <div class="mb-4">
                <label for="job_title" class="block text-sm font-medium text-slate-300 mb-2"><?php echo __('job_title'); ?> *</label>
                <input type="text" id="job_title" name="job_title" required 
                    class="input-field w-full px-4 py-3 rounded-lg"
                    placeholder="<?php echo __('job_title'); ?>">
            </div>
            <?php endif; ?>
            
            <button type="submit" class="btn-primary w-full py-3 rounded-lg font-semibold text-white transition mt-4">
                <?php echo __('join_conference'); ?>
            </button>
        </form>
        
        <div class="text-center mt-6">
            <a href="index.php" class="text-slate-400 hover:text-slate-300 text-sm"><?php echo __('back_to_code_entry'); ?></a>
        </div>
    </div>
</body>
</html>
