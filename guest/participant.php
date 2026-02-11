<?php
/**
 * Guest Participant Interface
 */
require_once __DIR__ . '/../includes/functions.php';

// Check participant session
if (empty($_SESSION['participant_id']) || empty($_SESSION['conference_id'])) {
    redirect('index.php');
}

$db = getDB();

// Get participant
$stmt = $db->prepare("SELECT p.*, c.name as conference_name, c.unique_code FROM participants p JOIN conferences c ON p.conference_id = c.id WHERE p.id = ? AND c.is_active = 1");
$stmt->execute([$_SESSION['participant_id']]);
$participant = $stmt->fetch();

if (!$participant) {
    session_destroy();
    redirect('index.php');
}

$conferenceId = $participant['conference_id'];

// Get conference language setting
$conference = getConferenceById($conferenceId);
$currentLang = $conference['language'] ?? 'no';
setLanguage($currentLang);

// Get active question
$stmt = $db->prepare("SELECT * FROM questions WHERE conference_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$conferenceId]);
$activeQuestion = $stmt->fetch();

// Handle question submission from guest
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_question'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $questionText = trim($_POST['guest_question'] ?? '');
        $isAnonymous = isset($_POST['anonymous']) ? 1 : 0;
        
        if (!empty($questionText)) {
            // Check for duplicate submission within last 5 seconds
            $stmt = $db->prepare("SELECT id FROM guest_questions 
                WHERE participant_id = ? AND question_text = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
            $stmt->execute([$_SESSION['participant_id'], $questionText]);
            
            if (!$stmt->fetch()) {
                $stmt = $db->prepare("INSERT INTO guest_questions (conference_id, participant_id, question_text, is_anonymous) VALUES (?, ?, ?, ?)");
                $stmt->execute([$conferenceId, $_SESSION['participant_id'], $questionText, $isAnonymous]);
                setFlashMessage('success', $currentLang === 'no' ? 'Spørsmål sendt!' : 'Question submitted!');
            }
        }
    }
    // Redirect to prevent form resubmission on refresh
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Debug logging for answer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('Answer POST: ' . print_r($_POST, true));
    error_log('Session participant_id: ' . ($_SESSION['participant_id'] ?? 'NOT SET'));
}

// Handle answer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    error_log('Processing answer submission...');
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        error_log('CSRF token verified');
        $questionId = intval($_POST['question_id'] ?? 0);
        
        // Check if already answered
        $stmt = $db->prepare("SELECT id FROM answers WHERE question_id = ? AND participant_id = ?");
        $stmt->execute([$questionId, $_SESSION['participant_id']]);
        if (!$stmt->fetch()) {
            $answerText = '';
            
            if (isset($_POST['answer'])) {
                if (is_array($_POST['answer'])) {
                    $answerText = implode(', ', $_POST['answer']);
                } else {
                    $answerText = $_POST['answer'];
                }
            } elseif (isset($_POST['rating'])) {
                $answerText = intval($_POST['rating']);
            } elseif (isset($_POST['wordcloud_text'])) {
                $answerText = trim($_POST['wordcloud_text']);
            }
            
            error_log('Answer text: ' . $answerText);
            if (!empty($answerText)) {
                $stmt = $db->prepare("INSERT INTO answers (question_id, participant_id, answer_text) VALUES (?, ?, ?)");
                $stmt->execute([$questionId, $_SESSION['participant_id'], $answerText]);
                error_log('Answer saved to database successfully');
            } else {
                error_log('Answer text was empty - not saved');
            }
        } else {
            error_log('User already answered this question');
        }
    } else {
        error_log('CSRF token verification failed');
    }
    // Redirect to prevent form resubmission on refresh
    $redirectUrl = '?conference_id=' . urlencode($participant['unique_code'] ?? '');
    header('Location: ' . $redirectUrl);
    exit;
}

// Get user's submitted questions
$stmt = $db->prepare("
    SELECT id, question_text, status, created_at 
    FROM guest_questions 
    WHERE participant_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['participant_id']]);
$myQuestions = $stmt->fetchAll();

// Check if user has answered active question
$hasAnswered = false;
if ($activeQuestion) {
    $stmt = $db->prepare("SELECT id FROM answers WHERE question_id = ? AND participant_id = ?");
    $stmt->execute([$activeQuestion['id'], $_SESSION['participant_id']]);
    $hasAnswered = $stmt->fetch() !== false;
}

$csrfToken = generateCSRFToken();

// Status translations
$statusText = [
    'pending' => $currentLang === 'no' ? 'Innsendt' : 'Submitted',
    'approved' => $currentLang === 'no' ? 'Godkjent' : 'Approved',
    'displayed' => $currentLang === 'no' ? 'Vist på skjerm' : 'Displayed',
    'rejected' => $currentLang === 'no' ? 'Avvist' : 'Rejected'
];

$statusClass = [
    'pending' => 'text-yellow-400',
    'approved' => 'text-green-400',
    'displayed' => 'text-blue-400',
    'rejected' => 'text-red-400'
];
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo e($participant['conference_name']); ?></title>
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
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        }
        .btn-secondary {
            background-color: #334155;
            border: 1px solid #475569;
        }
        .option-card {
            background-color: #334155;
            border: 2px solid #475569;
            transition: all 0.2s;
        }
        .option-card:hover {
            border-color: #3b82f6;
        }
        .option-card.selected {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }
    </style>
</head>
<body class="min-h-screen pb-20">
    <!-- Header -->
    <header class="card sticky top-0 z-10 border-b border-slate-700">
        <div class="max-w-lg mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="font-bold text-lg truncate"><?php echo e($participant['conference_name']); ?></h1>
            <a href="index.php" onclick="return confirm('<?php echo __('leave_conference'); ?>');" class="text-sm text-slate-400"><?php echo __('exit'); ?></a>
        </div>
    </header>

    <main class="max-w-lg mx-auto px-4 py-6">
        <?php showFlashMessage(); ?>
        
        <!-- Active Question -->
        <?php if ($activeQuestion): ?>
            <div class="card rounded-2xl p-6 mb-6">
                <div class="flex items-center gap-2 mb-4">
                    <span class="bg-red-500 w-2 h-2 rounded-full animate-pulse"></span>
                    <span class="text-sm font-semibold text-red-400"><?php echo strtoupper(__('live')); ?> <?php echo strtoupper(__('question')); ?></span>
                </div>
                
                <h2 class="text-xl font-bold mb-6"><?php echo e($activeQuestion['question_text']); ?></h2>
                
                <?php if ($hasAnswered): ?>
                    <div class="bg-green-900/50 border border-green-500 text-green-200 px-4 py-3 rounded-lg text-center">
                        ✅ <?php echo __('thank_you_answer_submitted'); ?>
                    </div>
                <?php else: ?>
                    <form method="POST" action="?conference_id=<?php echo urlencode($participant['unique_code'] ?? ''); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="question_id" value="<?php echo $activeQuestion['id']; ?>">
                        
                        <?php if ($activeQuestion['question_type'] === 'single'): 
                            $options = json_decode($activeQuestion['options'], true) ?? [];
                        ?>
                            <div class="space-y-2">
                                <?php foreach ($options as $option): ?>
                                    <label class="option-card block p-4 rounded-lg cursor-pointer">
                                        <input type="radio" name="answer" value="<?php echo e($option); ?>" required class="sr-only" onchange="document.querySelectorAll('.option-card').forEach(el => el.classList.remove('selected')); this.parentElement.classList.add('selected');">
                                        <span><?php echo e($option); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            
                        <?php elseif ($activeQuestion['question_type'] === 'multiple'): 
                            $options = json_decode($activeQuestion['options'], true) ?? [];
                        ?>
                            <div class="space-y-2">
                                <?php foreach ($options as $option): ?>
                                    <label class="option-card block p-4 rounded-lg cursor-pointer">
                                        <input type="checkbox" name="answer[]" value="<?php echo e($option); ?>" class="sr-only" onchange="this.parentElement.classList.toggle('selected')">
                                        <span><?php echo e($option); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            
                        <?php elseif ($activeQuestion['question_type'] === 'rating'): ?>
                            <div class="grid grid-cols-5 gap-2">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <label class="option-card text-center py-4 rounded-lg cursor-pointer">
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" required class="sr-only" onchange="document.querySelectorAll('.option-card').forEach(el => el.classList.remove('selected')); this.parentElement.classList.add('selected');">
                                        <span class="text-xl font-bold"><?php echo $i; ?></span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <div class="flex justify-between text-sm text-slate-400 mt-2">
                                <span><?php echo __('poor'); ?></span>
                                <span><?php echo __('excellent'); ?></span>
                            </div>
                            
                        <?php elseif ($activeQuestion['question_type'] === 'wordcloud'): ?>
                            <div class="mb-4">
                                <label class="block text-sm text-slate-400 mb-2"><?php echo $currentLang === 'no' ? 'Skriv et ord eller en kort setning:' : 'Enter a word or short phrase:'; ?></label>
                                <input type="text" name="wordcloud_text" required maxlength="50"
                                    class="input-field w-full px-4 py-3 rounded-lg text-center text-xl"
                                    placeholder="<?php echo __('your_question'); ?>">
                            </div>
                        <?php endif; ?>
                        
                        <button type="submit" name="submit_answer" class="btn-primary w-full py-3 rounded-lg font-semibold text-white mt-4">
                            <?php echo __('submit_answer'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card rounded-2xl p-6 mb-6 text-center">
                <div class="text-4xl mb-4">⏳</div>
                <p class="text-lg text-slate-300"><?php echo __('waiting_for_questions'); ?></p>
                <p class="text-sm text-slate-500 mt-2"><?php echo __('waiting_for_admin'); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Submit Question Button -->
        <button onclick="document.getElementById('askModal').classList.remove('hidden')" class="btn-secondary w-full py-3 rounded-lg font-semibold mb-6">
            📝 <?php echo __('ask_a_question'); ?>
        </button>
        
        <!-- My Questions -->
        <?php if (!empty($myQuestions)): ?>
            <div class="card rounded-2xl p-4">
                <h3 class="font-semibold mb-4"><?php echo __('my_questions'); ?></h3>
                <div class="space-y-3">
                    <?php foreach ($myQuestions as $q): ?>
                        <div class="bg-slate-800 rounded-lg p-3">
                            <p class="text-sm"><?php echo e($q['question_text']); ?></p>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-xs text-slate-500"><?php echo date('H:i', strtotime($q['created_at'])); ?></span>
                                <span class="text-xs font-medium <?php echo $statusClass[$q['status']]; ?>"><?php echo $statusText[$q['status']]; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Ask Question Modal -->
    <div id="askModal" class="hidden fixed inset-0 bg-black/80 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="card rounded-2xl p-6 w-full max-w-sm">
            <h3 class="text-xl font-bold mb-4"><?php echo __('ask_a_question'); ?></h3>
            <form method="POST" action="?conference_id=<?php echo urlencode($participant['unique_code'] ?? ''); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <textarea name="guest_question" required rows="3" maxlength="500"
                    class="input-field w-full px-4 py-3 rounded-lg mb-4"
                    placeholder="<?php echo __('type_question_here'); ?>"></textarea>
                <label class="flex items-center mb-4 cursor-pointer">
                    <input type="checkbox" name="anonymous" class="rounded border-slate-500 bg-slate-700 text-blue-500">
                    <span class="ml-2 text-sm text-slate-400"><?php echo __('ask_anonymously'); ?></span>
                </label>
                <div class="flex gap-2">
                    <button type="button" onclick="document.getElementById('askModal').classList.add('hidden')" class="btn-secondary flex-1 py-3 rounded-lg font-semibold">
                        <?php echo __('cancel'); ?>
                    </button>
                    <button type="submit" name="submit_question" class="btn-primary flex-1 py-3 rounded-lg font-semibold text-white">
                        <?php echo __('submit'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Smart auto-refresh: only refresh when no input is focused and no modal is open
    let refreshTimer;
    function scheduleRefresh() {
        refreshTimer = setTimeout(() => {
            // Only refresh if:
            // 1. No input or textarea is focused
            // 2. The ask modal is not open
            // 3. No form has been recently submitted (button not disabled)
            const activeElement = document.activeElement;
            const isInputFocused = activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA');
            const isModalOpen = !document.getElementById('askModal').classList.contains('hidden');
            const isSubmitting = document.querySelector('button[type="submit"]:disabled');
            
            if (!isInputFocused && !isModalOpen && !isSubmitting) {
                location.reload();
            } else {
                // Reschedule if conditions aren't met
                scheduleRefresh();
            }
        }, 10000);
    }
    scheduleRefresh();
    
    // Cancel refresh when user interacts with inputs
    document.querySelectorAll('input, textarea').forEach(input => {
        input.addEventListener('focus', () => clearTimeout(refreshTimer));
        input.addEventListener('blur', scheduleRefresh);
    });
    
    // Prevent double-click submission on all forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.textContent;
                submitBtn.textContent = submitBtn.name === 'submit_question' 
                    ? (document.documentElement.lang === 'no' ? 'Sender...' : 'Sending...')
                    : (document.documentElement.lang === 'no' ? 'Lagrer...' : 'Saving...');
                // Re-enable if submission takes too long (in case of error)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }, 5000);
            }
        });
    });
    </script>
</body>
</html>
