<?php
/**
 * Combined Dashboard - Questions and Guest Questions on one page
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Check auth
if (!isLoggedIn()) {
    redirect('/login.php');
}

// Get conference ID
$conferenceId = intval($_GET['conference_id'] ?? 0);
if (!$conferenceId) {
    setFlashMessage('error', 'Conference ID required');
    redirect('/admin/dashboard.php');
}

// Get conference details
$db = getDB();
$stmt = $db->prepare("SELECT * FROM conferences WHERE id = ? AND user_id = ?");
$stmt->execute([$conferenceId, $_SESSION['user_id']]);
$conference = $stmt->fetch();

if (!$conference) {
    setFlashMessage('error', 'Conference not found or access denied');
    redirect('/admin/dashboard.php');
}

// Get all questions for this conference
$stmt = $db->prepare("SELECT * FROM questions WHERE conference_id = ? ORDER BY created_at DESC");
$stmt->execute([$conferenceId]);
$questions = $stmt->fetchAll();

// Get currently active question
$stmt = $db->prepare("SELECT * FROM questions WHERE conference_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$conferenceId]);
$activeQuestion = $stmt->fetch();

// Get pending guest questions
$stmt = $db->prepare("
    SELECT gq.*, p.name as participant_name 
    FROM guest_questions gq 
    JOIN participants p ON gq.participant_id = p.id 
    WHERE gq.conference_id = ? AND gq.status = 'pending'
    ORDER BY gq.created_at ASC
");
$stmt->execute([$conferenceId]);
$pendingGuestQuestions = $stmt->fetchAll();

// Get currently displayed guest question
$stmt = $db->prepare("
    SELECT gq.*, p.name as participant_name 
    FROM guest_questions gq 
    JOIN participants p ON gq.participant_id = p.id 
    WHERE gq.conference_id = ? AND gq.status = 'displayed'
    LIMIT 1
");
$stmt->execute([$conferenceId]);
$displayedGuestQuestion = $stmt->fetch();

// Get approved questions (not displayed) for display dropdown
$stmt = $db->prepare("
    SELECT gq.*, p.name as participant_name 
    FROM guest_questions gq 
    JOIN participants p ON gq.participant_id = p.id 
    WHERE gq.conference_id = ? AND gq.status = 'approved'
    ORDER BY gq.created_at DESC
");
$stmt->execute([$conferenceId]);
$approvedGuestQuestions = $stmt->fetchAll();

$pageTitle = 'Combined Dashboard - ' . $conference['name'];
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold"><?php echo e($conference['name']); ?></h1>
                    <p class="text-blue-200 mt-1">Kode: <span class="font-mono bg-white/20 px-2 py-1 rounded"><?php echo e($conference['unique_code']); ?></span></p>
                </div>
                <div class="flex gap-3">
                    <a href="../overlay/display.php?conference=<?php echo e($conference['uuid'] ?? ''); ?>" target="_blank" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition">Vis overlay</a>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php echo showFlashMessage(); ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- LEFT: Questions -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-blue-600 text-white px-6 py-4 flex justify-between items-center">
                    <h2 class="text-xl font-bold">Spørsmål fra konferansier</h2>
                    <a href="questions/create.php?conference_id=<?php echo $conferenceId; ?>" class="bg-white/20 hover:bg-white/30 text-white px-3 py-1 rounded text-sm transition">+ Nytt</a>
                </div>
                
                <div class="p-6">
                    <?php if (empty($questions)): ?>
                        <div class="text-center text-gray-500 py-8">
                            <p>Ingen spørsmål ennå.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($questions as $question): ?>
                                <div class="border rounded-lg p-4 <?php echo $question['is_active'] ? 'border-blue-500 bg-blue-50' : 'border-gray-200'; ?>">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-gray-900"><?php echo e($question['question_text']); ?></h3>
                                            <div class="text-sm text-gray-500 mt-1">Type: <?php echo e($question['question_type']); ?> | Svar: <?php echo $question['response_count']; ?></div>
                                        </div>
                                        <div class="flex flex-col gap-2 ml-4">
                                            <?php if ($question['is_active']): ?>
                                                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full text-center">Aktiv</span>
                                                <form method="POST" action="questions/deactivate.php" class="inline">
                                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                    <input type="hidden" name="conference_id" value="<?php echo $conferenceId; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Stopp</button>
                                                </form>
                                                <?php if ($question['show_results']): ?>
                                                    <form method="POST" action="questions/toggle-results.php" class="inline">
                                                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                        <input type="hidden" name="conference_id" value="<?php echo $conferenceId; ?>">
                                                        <input type="hidden" name="show_results" value="0">
                                                        <button type="submit" class="text-orange-600 hover:text-orange-800 text-sm">Skjul resultat</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="questions/toggle-results.php" class="inline">
                                                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                        <input type="hidden" name="conference_id" value="<?php echo $conferenceId; ?>">
                                                        <input type="hidden" name="show_results" value="1">
                                                        <button type="submit" class="text-green-600 hover:text-green-800 text-sm">Vis resultat</button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <form method="POST" action="questions/activate.php" class="inline">
                                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                    <input type="hidden" name="conference_id" value="<?php echo $conferenceId; ?>">
                                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-1 rounded transition">Aktiver</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: Guest Questions -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-purple-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Spørsmål fra publikum</h2>
                </div>
                
                <div class="p-6 space-y-6">
                    
                    <!-- Pending -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">
                            Venter på godkjenning 
                            <span id="pending-count-badge" class="bg-red-500 text-white text-xs px-2 py-0.5 rounded-full" style="<?php echo count($pendingGuestQuestions) > 0 ? '' : 'display: none;'; ?>">
                                <?php echo count($pendingGuestQuestions); ?>
                            </span>
                        </h3>
                        
                        <?php if (empty($pendingGuestQuestions)): ?>
                            <p class="text-gray-500 text-sm">Ingen nye spørsmål.</p>
                        <?php else: ?>
                            <div class="space-y-3 max-h-64 overflow-y-auto">
                                <?php foreach ($pendingGuestQuestions as $gq): ?>
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                        <p class="text-gray-800"><?php echo e($gq['question_text']); ?></p>
                                        <p class="text-xs text-gray-500 mt-1">Fra: <?php echo $gq['is_anonymous'] ? 'Anonym' : e($gq['participant_name']); ?></p>
                                        <div class="flex gap-2 mt-2">
                                            <form method="POST" action="guest-questions/approve.php" class="inline">
                                                <input type="hidden" name="guest_question_id" value="<?php echo $gq['id']; ?>">
                                                <input type="hidden" name="conference_id" value="<?php echo $conferenceId; ?>">
                                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1 rounded transition">Godkjenn</button>
                                            </form>
                                            <form method="POST" action="guest-questions/reject.php" class="inline">
                                                <input type="hidden" name="guest_question_id" value="<?php echo $gq['id']; ?>">
                                                <input type="hidden" name="conference_id" value="<?php echo $conferenceId; ?>">
                                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1 rounded transition">Avvis</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Displayed -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">Vises på skjerm</h3>
                        
                        <?php if ($displayedGuestQuestion): ?>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <p class="text-gray-800 font-medium"><?php echo e($displayedGuestQuestion['question_text']); ?></p>
                                <p class="text-sm text-gray-500 mt-1">Fra: <?php echo $displayedGuestQuestion['is_anonymous'] ? 'Anonym' : e($displayedGuestQuestion['participant_name']); ?></p>
                                <form method="POST" action="guest-questions/hide.php" class="mt-3">
                                    <input type="hidden" name="guest_question_id" value="<?php echo $displayedGuestQuestion['id']; ?>">
                                    <input type="hidden" name="conference_id" value="<?php echo $conferenceId; ?>">
                                    <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white text-sm px-4 py-2 rounded transition">Fjern fra skjerm</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                                <p class="text-gray-500">Ingen spørsmål vises.</p>
                                
                                <?php if (!empty($approvedGuestQuestions)): ?>
                                    <form method="POST" action="guest-questions/display.php" class="mt-3">
                                        <input type="hidden" name="conference_id" value="<?php echo $conferenceId; ?>">
                                        <select name="guest_question_id" class="border rounded px-3 py-2 text-sm w-full mb-2" required>
                                            <option value="">Velg spørsmål...</option>
                                            <?php foreach ($approvedGuestQuestions as $gq): ?>
                                                <option value="<?php echo $gq['id']; ?>"><?php echo e(substr($gq['question_text'], 0, 50)); ?><?php echo strlen($gq['question_text']) > 50 ? '...' : ''; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white text-sm px-4 py-2 rounded transition w-full">Vis på skjerm</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
            
        </div>
    </main>
</div>

<script>
    // AJAX polling for new guest questions
    const conferenceId = <?php echo $conferenceId; ?>;
    let lastPendingCount = <?php echo count($pendingGuestQuestions); ?>;
    
    function pollForNewQuestions() {
        fetch(`../api/pending-questions.php?conference_id=${conferenceId}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) return;
                
                // Update pending count badge
                const badge = document.getElementById('pending-count-badge');
                if (badge) {
                    badge.textContent = data.pendingCount;
                    badge.style.display = data.pendingCount > 0 ? 'inline' : 'none';
                }
                
                // If new questions arrived, reload the page
                if (data.pendingCount > lastPendingCount) {
                    console.log('New questions arrived! Reloading...');
                    location.reload();
                }
                
                // Update displayed question section if changed
                const displayedSection = document.querySelector('.displayed-section');
                if (data.displayed && displayedSection) {
                    // Could update DOM here instead of reload
                }
                
                lastPendingCount = data.pendingCount;
            })
            .catch(err => console.error('Poll error:', err));
    }
    
    // Poll every 5 seconds
    setInterval(pollForNewQuestions, 5000);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
