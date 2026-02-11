<?php
/**
 * Guest Landing Page
 */
require_once __DIR__ . '/../includes/functions.php';

// Handle language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], ['no', 'en'])) {
    setLanguage($_GET['lang']);
}
$currentLang = getCurrentLanguage();

$code = trim($_GET['code'] ?? '');
$error = '';
$conference = null;

if (!empty($code)) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM conferences WHERE unique_code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $conference = $stmt->fetch();
    
    if (!$conference) {
        $error = $currentLang === 'no' ? 'Ugyldig eller inaktiv konferansekode.' : 'Invalid or inactive conference code.';
    } else {
        // Store conference in session and redirect to registration
        $_SESSION['conference_id'] = $conference['id'];
        $_SESSION['conference_code'] = $code;
        redirect('register.php');
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo __('join_conference'); ?></title>
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
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="text-5xl mb-4">🎯</div>
            <h1 class="text-2xl font-bold"><?php echo __('join_conference'); ?></h1>
            <p class="text-slate-400 mt-2"><?php echo __('enter_conference_code'); ?></p>
            
            <!-- Language Switcher -->
            <div class="flex justify-center items-center space-x-2 mt-4">
                <a href="?lang=no" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'no' ? 'bg-blue-600 text-white font-bold' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'; ?>">Norsk</a>
                <a href="?lang=en" class="px-3 py-1 rounded text-sm <?php echo $currentLang === 'en' ? 'bg-blue-600 text-white font-bold' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'; ?>">English</a>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 px-4 py-3 rounded-lg mb-6 text-center">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="GET" action="" class="card rounded-2xl p-6 shadow-xl">
            <div class="mb-6">
                <label for="code" class="block text-sm font-medium text-slate-300 mb-2"><?php echo __('conference_code'); ?></label>
                <input type="text" id="code" name="code" required 
                    class="input-field w-full px-4 py-3 rounded-lg text-center text-lg font-mono uppercase tracking-wider"
                    placeholder="<?php echo strtoupper(__('enter_code')); ?>"
                    maxlength="20"
                    autocomplete="off">
            </div>
            
            <button type="submit" class="btn-primary w-full py-3 rounded-lg font-semibold text-white transition">
                <?php echo __('join'); ?>
            </button>
        </form>
        
        <div class="text-center mt-8 text-slate-500 text-sm">
            <p><?php echo __('scan_qr_or_enter_code'); ?></p>
        </div>
    </div>
</body>
</html>
