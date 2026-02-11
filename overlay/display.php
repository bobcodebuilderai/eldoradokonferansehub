<?php
/**
 * Overlay Display for OBS/Big Screen
 * Dynamic resolution based on conference settings
 * Session Independent - Uses conference UUID from URL
 */

require_once __DIR__ . '/../config/database.php';

// Prevent caching - always get fresh content
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Get conference UUID from URL parameter (no session needed)
$conferenceUuid = trim($_GET['conference'] ?? '');
$code = trim($_GET['code'] ?? '');

$db = getDB();

$conferenceId = 0;

if ($conferenceUuid) {
    // Lookup by UUID
    $stmt = $db->prepare("SELECT id FROM conferences WHERE uuid = ? AND is_active = 1");
    $stmt->execute([$conferenceUuid]);
    $result = $stmt->fetch();
    if ($result) {
        $conferenceId = $result['id'];
    }
} elseif ($code) {
    // Legacy: Lookup by code
    $stmt = $db->prepare("SELECT id FROM conferences WHERE unique_code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $result = $stmt->fetch();
    if ($result) {
        $conferenceId = $result['id'];
    }
}

if (!$conferenceId) {
    http_response_code(404);
    die('Conference not found');
}

// Get conference details
$stmt = $db->prepare("SELECT * FROM conferences WHERE id = ? AND is_active = 1");
$stmt->execute([$conferenceId]);
$conference = $stmt->fetch();

if (!$conference) {
    http_response_code(404);
    die('Conference not found or inactive');
}

// Get code for guest URL if not provided
if (empty($code)) {
    $code = $conference['unique_code'];
}

$conferenceId = $conference['id'];
$overlayBackground = $conference['overlay_background'] ?? 'graphic';

// Get conference screen resolution settings
$screenWidth = intval($conference['screen_width'] ?? 1920);
$screenHeight = intval($conference['screen_height'] ?? 1080);

// Set language from conference
$currentLang = $conference['language'] ?? 'no';
$langFile = __DIR__ . '/../includes/lang/' . $currentLang . '.php';
if (file_exists($langFile)) {
    require_once $langFile;
    // Define __() function using loaded translations
    if (!function_exists("__")) {
        function __($key) {
            global $currentLang;
            static $translations = null;
            if ($translations === null) {
                $langFile = __DIR__ . "/../includes/lang/" . $currentLang . ".php";
                $translations = file_exists($langFile) ? require $langFile : [];
            }
            return isset($translations[$key]) ? $translations[$key] : $key;
        }
    }
} else {
    // Minimal fallback translations
    function __($key) {
        $translations = [
            'open_overlay' => 'Open Overlay',
            'scan_to_join' => 'Scan to Join',
            'active_question' => 'Active Question',
            'questions_from_audience' => 'Questions from Audience',
            'participants' => 'Participants',
            'responses' => 'Responses'
        ];
        return $translations[$key] ?? $key;
    }
}

// Helper function for escaping
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// Get base URL for QR code
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    return $protocol . '://' . $host . $scriptDir;
}

// Get active question
$stmt = $db->prepare("SELECT * FROM questions WHERE conference_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$conferenceId]);
$activeQuestion = $stmt->fetch();

// Get answers for active question
$answers = [];
$answerData = [];
if ($activeQuestion && $activeQuestion['show_results']) {
    $stmt = $db->prepare("SELECT answer_text, COUNT(*) as count FROM answers WHERE question_id = ? GROUP BY answer_text ORDER BY count DESC");
    $stmt->execute([$activeQuestion['id']]);
    $answers = $stmt->fetchAll();
    
    foreach ($answers as $ans) {
        $answerData[$ans['answer_text']] = $ans['count'];
    }
}

// Get displayed guest question
$stmt = $db->prepare("
    SELECT gq.*, p.name as participant_name 
    FROM guest_questions gq 
    JOIN participants p ON gq.participant_id = p.id 
    WHERE gq.conference_id = ? AND gq.status = 'displayed'
    ORDER BY gq.created_at DESC 
    LIMIT 1
");
$stmt->execute([$conferenceId]);
$displayedQuestion = $stmt->fetch();

// Get QR code URL
$guestUrl = getBaseUrl() . '/../guest/?code=' . urlencode($code);

// Determine background style
$bodyStyle = '';
if ($overlayBackground === 'transparent') {
    $bodyStyle = 'background: transparent;';
} else {
    $bodyStyle = 'background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #1e1b4b 100%);';
}
?>
<!DOCTYPE html>
<html lang="<?php echo e($currentLang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($conference['name']); ?> - Overlay</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            width: <?php echo $screenWidth; ?>px;
            height: <?php echo $screenHeight; ?>px;
            <?php echo $bodyStyle; ?>
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow: hidden;
            display: flex;
        }
        
        /* Left Panel - QR Code */
        .qr-panel {
            width: 400px;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            border-right: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .qr-panel h2 {
            font-size: 32px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .qr-code {
            background: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 20px;
        }
        
        .qr-code img {
            width: 280px;
            height: 280px;
        }
        
        .join-text {
            font-size: 24px;
            text-align: center;
            color: #94a3b8;
        }
        
        .code-display {
            font-size: 48px;
            font-weight: bold;
            letter-spacing: 8px;
            margin-top: 10px;
            color: #60a5fa;
        }
        
        /* Center Panel - Question & Results */
        .main-panel {
            flex: 1;
            padding: 60px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .conference-title {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 40px;
            text-align: center;
            color: #e2e8f0;
        }
        
        .active-question {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 50px;
            margin-bottom: 40px;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .question-label {
            font-size: 24px;
            color: #60a5fa;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin-bottom: 20px;
        }
        
        .question-text {
            font-size: 56px;
            font-weight: bold;
            line-height: 1.3;
        }
        
        .results-container {
            overflow: hidden;
            flex: 1;
            display: flex;
            gap: 40px;
        }
        
        .chart-container {
            overflow: hidden;
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px;
            display: flex;
            flex-direction: column;
        }
        
        .chart-container h3 {
            font-size: 28px;
            margin-bottom: 20px;
            color: #94a3b8;
        }
        
        #resultsChart {
            max-height: 100%;
            flex: 1;
        }
        
        .wordcloud-container {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 20px;
            overflow: hidden;
        }
        
        .wordcloud-item {
            transition: all 0.5s ease;
        }
        
        .waiting-message {
            font-size: 36px;
            color: #64748b;
            text-align: center;
            padding: 60px;
        }

        .empty-state {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Right Panel - Guest Questions */
        .questions-panel {
            width: 800px;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            border-left: 2px solid rgba(255, 255, 255, 0.1);
            padding: 40px;
            display: flex;
            flex-direction: column;
        }
        
        .questions-panel h2 {
            font-size: 36px;
            margin-bottom: 30px;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .questions-panel h2::before {
            content: '💬';
        }
        
        .displayed-question {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            border-radius: 20px;
            padding: 40px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            50% { box-shadow: 0 0 30px 10px rgba(59, 130, 246, 0.2); }
        }
        
        .displayed-question-text {
            font-size: 42px;
            font-weight: bold;
            line-height: 1.4;
        }
        
        .displayed-question-author {
            font-size: 24px;
            margin-top: 20px;
            opacity: 0.8;
        }
        
        .no-question {
            font-size: 32px;
            color: #64748b;
            text-align: center;
            padding: 60px;
        }
        
        .stats-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 60px;
            font-size: 24px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stat-value {
            font-weight: bold;
            color: #60a5fa;
        }
    
        /* Animations */
        @keyframes slideFromRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideFromTop {
            from {
                opacity: 0;
                transform: translateY(-100px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideFromBottom {
            from {
                opacity: 0;
                transform: translateY(100px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-from-right {
            animation: slideFromRight 0.6s ease-out forwards;
        }
        
        .animate-from-top {
            animation: slideFromTop 0.6s ease-out forwards;
        }
        
        .animate-from-bottom {
            animation: slideFromBottom 0.6s ease-out forwards;
        }
        
        /* Exit animations */
        @keyframes slideToRight {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100px); }
        }

        @keyframes slideToTop {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-100px); }
        }

        @keyframes slideToBottom {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(100px); }
        }

        .animate-out-right { animation: slideToRight 0.5s ease-in forwards; }
        .animate-out-top { animation: slideToTop 0.5s ease-in forwards; }
        .animate-out-bottom { animation: slideToBottom 0.5s ease-in forwards; }

        /* Hidden state */
        .panel-hidden {
            opacity: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <!-- QR Code Panel -->
    <div class="qr-panel">
        <h2><?php echo __('scan_to_join'); ?></h2>
        <div id="qrcode" class="qr-code"></div>
        <div class="join-text"><?php echo __('or_visit_with_code'); ?></div>
        <div class="code-display"><?php echo e($code); ?></div>
    </div>
    
    <!-- Main Panel -->
    <div class="main-panel">
        <!-- Active Question Panel - always in DOM, hidden/shown via JS -->
        <div id="active-question-panel" class="active-question" <?php echo !$activeQuestion ? 'style="display: none;"' : ''; ?>>
            <?php if ($activeQuestion): ?>
                <div class="question-label"><?php echo __('active_question'); ?></div>
                <div class="question-text"><?php echo e($activeQuestion['question_text']); ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Results Panel - shown when show_results is true -->
        <?php if ($activeQuestion && $activeQuestion['show_results'] && !empty($answers)): ?>
            <div id="results-panel" class="results-container">
                <?php if ($activeQuestion['chart_type'] === 'wordcloud'): ?>
                    <div class="wordcloud-container" id="wordcloud">
                        <?php 
                        $maxCount = max(array_column($answers, 'count'));
                        foreach ($answers as $answer): 
                            $size = min(72, max(16, 16 + ($answer['count'] / $maxCount) * 56));
                            $opacity = 0.5 + ($answer['count'] / $maxCount) * 0.5;
                        ?>
                            <span class="wordcloud-item" style="font-size: <?php echo $size; ?>px; opacity: <?php echo $opacity; ?>;">
                                <?php echo e($answer['answer_text']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="chart-container">
                        <canvas id="resultsChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>    
    <!-- Guest Questions Panel - always in DOM, hidden/shown via JS -->
    <div id="guest-questions-panel" class="questions-panel" <?php echo !$displayedQuestion ? 'style="display: none;"' : ''; ?>>
        <h2><?php echo __('questions_from_audience'); ?></h2>
        <?php if ($displayedQuestion): ?>
            <div class="displayed-question">
                <div class="displayed-question-text"><?php echo e($displayedQuestion['question_text']); ?></div>
                <?php if (!$displayedQuestion['is_anonymous']): ?>
                    <div class="displayed-question-author">— <?php echo e($displayedQuestion['participant_name']); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-item">
            <span><?php echo __('participants'); ?>:</span>
            <span class="stat-value" id="participantCount">-</span>
        </div>
        <div class="stat-item">
            <span><?php echo __('responses'); ?>:</span>
            <span class="stat-value" id="responseCount"><?php echo array_sum(array_column($answers, 'count')); ?></span>
        </div>
    </div>
    
    <script>
    // Generate QR Code
    new QRCode(document.getElementById('qrcode'), {
        text: <?php echo json_encode($guestUrl); ?>,
        width: 280,
        height: 280,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
    
    // Global variables - MUST be declared before use
    let eventSource = null;
    let reconnectTimer = null;
    let isTransitioning = false;
    let resultsChart = null;
    
    // State tracking for smooth transitions
    let lastStates = {
        activeQuestionId: <?php echo json_encode($activeQuestion ? $activeQuestion['id'] : null); ?>,
        showResults: <?php echo json_encode($activeQuestion && $activeQuestion['show_results']); ?>,
        guestQuestionId: <?php echo json_encode($displayedQuestion ? $displayedQuestion['id'] : null); ?>,
        responseCount: <?php echo json_encode(array_sum(array_column($answers, 'count'))); ?>,
        participantCount: 0,
        responseData: null
    };

    // Entry animations on page load
    document.addEventListener('DOMContentLoaded', function() {
        const questionPanel = document.getElementById('active-question-panel');
        const resultsPanel = document.getElementById('results-panel');
        const guestPanel = document.getElementById('guest-questions-panel');
        
        if (questionPanel) questionPanel.classList.add('animate-from-top');
        if (resultsPanel) resultsPanel.classList.add('animate-from-bottom');
        if (guestPanel) guestPanel.classList.add('animate-from-right');
        
        // Start SSE connection
        connectSSE();
    });
    
    <?php if ($activeQuestion && $activeQuestion['show_results'] && !empty($answers) && $activeQuestion['chart_type'] !== 'wordcloud'): ?>
    // Chart.js - Initialize and assign to resultsChart for live updates
    (function() {
        const ctx = document.getElementById('resultsChart').getContext('2d');
        resultsChart = new Chart(ctx, {
            type: <?php echo json_encode($activeQuestion['chart_type'] === 'pie' ? 'pie' : 'bar'); ?>,
            data: {
                labels: <?php echo json_encode(array_keys($answerData)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($answerData)); ?>,
                    backgroundColor: [
                        '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981',
                        '#6366f1', '#14b8a6', '#f97316', '#84cc16', '#06b6d4'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: <?php echo json_encode($activeQuestion['chart_type'] === 'bar_horizontal' ? 'y' : 'x'); ?>,
                plugins: {
                    legend: {
                        display: <?php echo json_encode($activeQuestion['chart_type'] === 'pie'); ?>,
                        labels: {
                            color: '#fff',
                            font: { size: 20 }
                        }
                    }
                },
                scales: {
                    x: {
                        display: <?php echo json_encode($activeQuestion['chart_type'] !== 'pie'); ?>,
                        ticks: { color: '#94a3b8', font: { size: 18 } },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    },
                    y: {
                        display: <?php echo json_encode($activeQuestion['chart_type'] !== 'pie'); ?>,
                        ticks: { color: '#94a3b8', font: { size: 18 } },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    }
                }
            }
        });
    })();
    <?php endif; ?>
    
    // SSE Connection for live updates
    
    // Update counters only (no animations)
    function updateCounters(data) {
        if (data.participants !== undefined && data.participants !== lastStates.participantCount) {
            document.getElementById('participantCount').textContent = data.participants;
            lastStates.participantCount = data.participants;
        }
        
        if (data.responses !== undefined && data.responses !== lastStates.responseCount) {
            document.getElementById('responseCount').textContent = data.responses;
            lastStates.responseCount = data.responses;
        }
    }
    
    // Update chart data for live updates (no full re-render)
    function updateChartData(responseData, question) {
        if (!resultsChart && question.chart_type !== 'wordcloud') return;
        
        if (question.chart_type === 'wordcloud') {
            // Re-render wordcloud with new data
            renderWordcloud(responseData);
        } else {
            // Update Chart.js with new data
            const labels = responseData.map(item => item.answer_text);
            const counts = responseData.map(item => parseInt(item.count));
            
            resultsChart.data.labels = labels;
            resultsChart.data.datasets[0].data = counts;
            resultsChart.update('none'); // 'none' mode = no animation, just update
        }
    }
    
    // Render wordcloud from answer array
    function renderWordcloud(answers) {
        const container = document.getElementById('wordcloud');
        if (!container) return;
        
        // Normalize answers to strings (handle both ["text", "text"] and [{answer_text: "text"}])
        const normalizedAnswers = answers.map(a => {
            if (typeof a === 'string') return a;
            if (a && typeof a === 'object') {
                // Handle object format from API
                return a.answer_text || a.text || String(a);
            }
            return String(a);
        });
        
        // Count frequencies
        const freq = {};
        normalizedAnswers.forEach(answer => {
            freq[answer] = (freq[answer] || 0) + 1;
        });
        
        // Sort by frequency
        const sorted = Object.entries(freq).sort((a, b) => b[1] - a[1]);
        
        // Build HTML
        let html = '';
        const maxCount = sorted[0]?.[1] || 1;
        
        sorted.forEach(([text, count]) => {
            const size = 16 + (count / maxCount) * 48; // 16px to 64px
            const opacity = 0.5 + (count / maxCount) * 0.5;
            html += `<span style="font-size: ${size}px; opacity: ${opacity}; padding: 8px 16px;">${escapeHtml(text)}</span>`;
        });
        
        container.innerHTML = html;
    }
    
    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize chart with data
    function initChart(answers, chartType) {
        const canvas = document.getElementById('resultsChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        // Destroy existing chart if any
        if (resultsChart) {
            resultsChart.destroy();
            resultsChart = null;
        }
        
        // Prepare data
        const labels = answers.map(a => a.answer_text);
        const data = answers.map(a => parseInt(a.count));
        
        const backgroundColors = [
            '#60a5fa', '#34d399', '#f472b6', '#fbbf24', '#a78bfa',
            '#f87171', '#2dd4bf', '#fb923c', '#e879f9', '#818cf8'
        ];
        
        resultsChart = new Chart(ctx, {
            type: chartType === 'pie' ? 'pie' : 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '<?php echo __('responses'); ?>',
                    data: data,
                    backgroundColor: backgroundColors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: chartType === 'bar_horizontal' ? 'y' : 'x',
                animation: {
                    duration: 500 // Quick animation for live updates
                },
                plugins: {
                    legend: {
                        display: chartType === 'pie',
                        position: 'right',
                        labels: {
                            color: '#e2e8f0',
                            font: { size: 18 }
                        }
                    }
                },
                scales: chartType !== 'pie' ? {
                    x: {
                        ticks: { color: '#94a3b8', font: { size: 16 } },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#94a3b8', font: { size: 16 } },
                        grid: { color: 'rgba(255,255,255,0.1)' }
                    }
                } : {}
            }
        });
    }
    
    // SSE Connection setup
    function connectSSE() {
        if (eventSource) {
            eventSource.close();
        }
        
        const conferenceId = <?php echo json_encode($conferenceId); ?>;
        // Add cache-busting timestamp
        const timestamp = Date.now();
        eventSource = new EventSource(`../api/sse.php?conference=<?php echo e($conference['uuid']); ?>&_=${timestamp}`);
        
        eventSource.onopen = function() {
            console.log('✅ SSE connected at', new Date().toLocaleTimeString());
            if (reconnectTimer) {
                clearTimeout(reconnectTimer);
                reconnectTimer = null;
            }
        };
        
        eventSource.onmessage = function(event) {
            console.log('SSE message:', event.data);
        };
        
        eventSource.addEventListener('update', function(event) {
            const data = JSON.parse(event.data);
            if (data.error) {
                console.error('SSE error:', data.error);
                return;
            }
            handleStateUpdate(data);
        });
        
        eventSource.addEventListener('connected', function(event) {
            console.log('SSE:', JSON.parse(event.data));
        });
        
        eventSource.addEventListener('ping', function(event) {
            // Keepalive received
        });
        
        eventSource.onerror = function(error) {
            console.error('❌ SSE error at', new Date().toLocaleTimeString(), error);
            eventSource.close();
            // Reconnect after 3 seconds
            reconnectTimer = setTimeout(connectSSE, 3000);
        };
    }
    
    function handleStateUpdate(data) {
        // Don't process if transitioning
        if (isTransitioning) return;
        
        // Normalize types (SSE sends integers, PHP initializes booleans)
        const currentQuestionId = data.activeQuestionId || null;
        const currentGuestId = data.displayedGuestQuestion ? data.displayedGuestQuestion.id : null;
        const currentShowResults = data.showResults ? true : false;
        const lastShowResultsNorm = lastStates.showResults ? true : false;
        
        // Handle live chart updates (same question, results showing, but new answers)
        if (currentQuestionId === lastStates.activeQuestionId && 
            currentShowResults && lastShowResultsNorm &&
            data.responses !== lastStates.responseCount) {
            
            updateChartData(data.responseData, data.question);
            updateCounters(data);
            lastStates.responseCount = data.responses;
            lastStates.responseData = data.responseData;
            return;
        }
        
        // Handle question change
        if (currentQuestionId !== lastStates.activeQuestionId) {
            isTransitioning = true;
            // Clear chart instance when question changes
            if (resultsChart) {
                resultsChart.destroy();
                resultsChart = null;
            }
            transitionQuestion(lastStates.activeQuestionId, currentQuestionId, data.question, data.responseData);
            lastStates.activeQuestionId = currentQuestionId;
            lastStates.showResults = currentShowResults;
            lastStates.responseCount = data.responses;
            lastStates.responseData = data.responseData;
            setTimeout(() => { isTransitioning = false; }, 600);
            return;
        }
        
        // Handle results toggle (only if same question)
        if (currentQuestionId === lastStates.activeQuestionId && 
            currentShowResults !== lastShowResultsNorm) {
            isTransitioning = true;
            transitionResults(currentShowResults, currentQuestionId, data.responseData);
            lastStates.showResults = currentShowResults;
            lastStates.responseCount = data.responses;
            lastStates.responseData = data.responseData;
            setTimeout(() => { isTransitioning = false; }, 600);
            return;
        }
        
        // Handle guest question change
        if (currentGuestId !== lastStates.guestQuestionId) {
            isTransitioning = true;
            transitionGuestQuestion(lastStates.guestQuestionId, currentGuestId, data.displayedGuestQuestion);
            lastStates.guestQuestionId = currentGuestId;
            setTimeout(() => { isTransitioning = false; }, 600);
            return;
        }
        
        // Only update counters
        updateCounters(data);
    }
    
    // Transition functions with data from SSE
    function transitionQuestion(oldId, newId, questionData, responseData) {
        const panel = document.getElementById('active-question-panel');
        const resultsPanel = document.getElementById('results-panel');
        
        if (oldId && panel) {
            // First hide results if showing
            if (resultsPanel && resultsPanel.style.display !== 'none') {
                resultsPanel.classList.add('animate-out-bottom');
            }
            
            panel.classList.add('animate-out-top');
            setTimeout(() => {
                if (newId && questionData) {
                    renderQuestionPanel(questionData, responseData);
                } else {
                    panel.innerHTML = '';
                    panel.style.display = 'none';
                    panel.classList.remove('animate-out-top', 'animate-from-top', 'animate-in-top');
                    // Hide results panel completely
                    if (resultsPanel) {
                        resultsPanel.style.display = 'none';
                        resultsPanel.classList.remove('animate-out-bottom');
                    }
                }
            }, 500);
        } else if (newId && questionData) {
            renderQuestionPanel(questionData, responseData);
        }
    }
    
    function fetchResultsAndRender(questionId) {
        fetch(`../api/question.php?conference_id=<?php echo $conferenceId; ?>`)
            .then(r => r.json())
            .then(data => {
                if (data.hasQuestion && data.question.show_results) {
                    renderResults(data.question, data.answers);
                }
            });
    }
    
    function renderQuestionPanel(question, responseData) {
        const panel = document.getElementById('active-question-panel');
        
        // Build HTML for question
        let html = `
            <div class="question-label"><?php echo __('active_question'); ?></div>
            <div class="question-text">${escapeHtml(question.question_text)}</div>
        `;
        
        panel.innerHTML = html;
        panel.style.display = 'block';
        panel.classList.remove('animate-out-top');
        panel.classList.add('animate-from-top');
        
        // Render results if showing (using responseData from SSE if available)
        if (question.show_results) {
            if (responseData && responseData.length > 0) {
                renderResults(question, responseData);
            } else {
                // Fallback: fetch from API
                fetch(`../api/question.php?conference_id=<?php echo $conferenceId; ?>`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.hasQuestion && data.answers) {
                            renderResults(data.question, data.answers);
                        }
                    });
            }
        } else {
            // Hide results panel
            const resultsPanel = document.getElementById('results-panel');
            if (resultsPanel) {
                resultsPanel.classList.add('animate-out-bottom');
                setTimeout(() => {
                    resultsPanel.style.display = 'none';
                    resultsPanel.classList.remove('animate-out-bottom', 'animate-from-bottom');
                }, 500);
            }
        }
    }
    
    function renderResults(question, answers) {
        if (!answers || answers.length === 0) return;
        
        const panel = document.getElementById('active-question-panel');
        let resultsPanel = document.getElementById('results-panel');
        
        if (!resultsPanel) {
            // Create results panel
            resultsPanel = document.createElement('div');
            resultsPanel.id = 'results-panel';
            resultsPanel.className = 'results-container animate-from-bottom';
            panel.after(resultsPanel);
        }
        
        resultsPanel.style.display = 'flex';
        resultsPanel.classList.remove('animate-out-bottom', 'panel-hidden');
        resultsPanel.classList.add('animate-from-bottom');
        
        if (question.chart_type === 'wordcloud') {
            // Build container for wordcloud
            resultsPanel.innerHTML = '<div class="wordcloud-container" id="wordcloud"></div>';
            // Handle both formats: array of strings (from SSE) or array of objects (from API)
            if (answers.length > 0 && typeof answers[0] === 'string') {
                renderWordcloud(answers);
            } else {
                // Convert from {answer_text, count} format to flat array for wordcloud
                const wordArray = [];
                answers.forEach(a => {
                    for (let i = 0; i < parseInt(a.count); i++) {
                        wordArray.push(a.answer_text);
                    }
                });
                renderWordcloud(wordArray);
            }
        } else {
            resultsPanel.innerHTML = '<div class="chart-container"><canvas id="resultsChart"></canvas></div>';
            setTimeout(() => initChart(answers, question.chart_type), 50);
        }
    }
    
    function transitionResults(show, questionId, responseData) {
        const panel = document.getElementById('results-panel');
        
        if (!show && panel) {
            // Only remove results, keep question
            panel.classList.add('animate-out-bottom');
            setTimeout(() => {
                panel.style.display = 'none';
                panel.classList.remove('animate-out-bottom');
            }, 500);
        } else if (show) {
            // Use responseData from SSE if available, otherwise fetch
            if (responseData && responseData.length > 0) {
                // Need to get question data for chart type
                fetch(`../api/question.php?conference_id=<?php echo $conferenceId; ?>`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.hasQuestion) {
                            renderResults(data.question, responseData);
                        }
                    });
            } else {
                // Fetch and show results
                fetchResultsAndRender(questionId);
            }
        }
    }
    
    function transitionGuestQuestion(oldId, newId, questionData) {
        const panel = document.getElementById('guest-questions-panel');
        
        if (oldId && panel) {
            // Fade out entire column
            panel.classList.add('animate-out-right');
            setTimeout(() => {
                panel.style.display = 'none';
                panel.classList.remove('animate-out-right');
                
                if (newId && questionData) {
                    // Show column and render new question
                    panel.style.display = 'flex';
                    panel.classList.add('animate-from-right');
                    renderGuestQuestionPanel(questionData);
                }
            }, 500);
        } else if (newId && questionData && panel) {
            // Show column if hidden
            if (panel.style.display === 'none') {
                panel.style.display = 'flex';
                panel.classList.add('animate-from-right');
            }
            renderGuestQuestionPanel(questionData);
        }
    }
    
    function renderGuestQuestionPanel(question) {
        const panel = document.getElementById('guest-questions-panel');
        const author = question.is_anonymous ? '<?php echo __('anonymous'); ?>' : escapeHtml(question.participant_name);
        const authorHtml = !question.is_anonymous ? `<div class="displayed-question-author">— ${author}</div>` : '';
        
        panel.style.display = 'flex';
        panel.innerHTML = `
            <h2><?php echo __('questions_from_audience'); ?></h2>
            <div class="displayed-question">
                <div class="displayed-question-text">${escapeHtml(question.question_text)}</div>
                ${authorHtml}
            </div>
        `;
        
        panel.classList.remove('animate-out-right');
        panel.classList.add('animate-from-right');
    }
    
    // Handle page visibility changes
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Pause updates when tab is hidden
            if (eventSource) {
                eventSource.close();
            }
        } else {
            // Reconnect when tab becomes visible
            connectSSE();
        }
    });
    
    // Stop SSE when leaving page
    window.addEventListener('beforeunload', function() {
        if (eventSource) {
            eventSource.close();
        }
    });
    </script>
</body>
</html>
