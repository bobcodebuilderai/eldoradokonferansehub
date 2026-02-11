<?php
/**
 * Run of Show Stage Display
 * Shows countdown, current item, and upcoming items for presenters
 */

require_once __DIR__ . '/../config/database.php';

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Get conference UUID
$conferenceUuid = trim($_GET['conference'] ?? '');
$dayNumber = intval($_GET['day'] ?? 1);

if (!$conferenceUuid) {
    http_response_code(404);
    die('Conference UUID required');
}

$db = getDB();

// Lookup conference by UUID
$stmt = $db->prepare("SELECT * FROM conferences WHERE uuid = ? AND is_active = 1");
$stmt->execute([$conferenceUuid]);
$conference = $stmt->fetch();

if (!$conference) {
    http_response_code(404);
    die('Conference not found');
}

$conferenceId = $conference['id'];

// Get active block and upcoming blocks
require_once __DIR__ . '/../includes/runofshow.php';

$activeBlock = getActiveBlock($conferenceId);
$upcomingBlocks = getUpcomingBlocks($conferenceId, 3);

// Calculate countdown if active
$countdown = null;
if ($activeBlock) {
    $countdown = getBlockCountdown($activeBlock);
}

// Get all blocks for this day for timeline
$allBlocks = getROSBlocks($conferenceId, $dayNumber);
?>
<!DOCTYPE html>
<html lang="no">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stage Display - <?php echo e($conference['name']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            width: 1920px;
            height: 1080px;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #1e1b4b 100%);
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background: rgba(0, 0, 0, 0.3);
            padding: 30px 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .header h1 {
            font-size: 48px;
            font-weight: bold;
        }
        
        .header .time {
            font-size: 72px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
        }
        
        .main-content {
            flex: 1;
            display: flex;
            padding: 40px 60px;
            gap: 60px;
        }
        
        /* Current Block / Countdown */
        .current-section {
            flex: 2;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 30px;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            border: 3px solid rgba(255, 255, 255, 0.1);
        }
        
        .current-label {
            font-size: 32px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 8px;
            margin-bottom: 30px;
        }
        
        .current-title {
            font-size: 72px;
            font-weight: bold;
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        .current-person {
            font-size: 48px;
            color: #60a5fa;
            margin-bottom: 40px;
        }
        
        .countdown {
            font-size: 200px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            color: #34d399;
            text-shadow: 0 0 30px rgba(52, 211, 153, 0.5);
        }
        
        .countdown.warning {
            color: #fbbf24;
            text-shadow: 0 0 30px rgba(251, 191, 36, 0.5);
        }
        
        .countdown.danger {
            color: #f87171;
            text-shadow: 0 0 30px rgba(248, 113, 113, 0.5);
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            margin-top: 40px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #34d399, #60a5fa);
            border-radius: 10px;
            transition: width 1s linear;
        }
        
        /* Upcoming Section */
        .upcoming-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .upcoming-title {
            font-size: 36px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 4px;
        }
        
        .upcoming-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px;
            border-left: 6px solid #60a5fa;
        }
        
        .upcoming-item:nth-child(2) { border-left-color: #34d399; }
        .upcoming-item:nth-child(3) { border-left-color: #f472b6; }
        
        .upcoming-time {
            font-size: 28px;
            color: #60a5fa;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .upcoming-item-title {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .upcoming-person {
            font-size: 24px;
            color: #94a3b8;
        }
        
        /* Waiting State */
        .waiting-state {
            flex: 2;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        .waiting-icon {
            font-size: 200px;
            margin-bottom: 40px;
        }
        
        .waiting-text {
            font-size: 64px;
            color: #94a3b8;
        }
        
        .next-up {
            font-size: 48px;
            color: #60a5fa;
            margin-top: 30px;
        }
        
        /* Tech indicators */
        .tech-indicators {
            display: flex;
            gap: 20px;
            margin-top: 30px;
            justify-content: center;
        }
        
        .tech-item {
            background: rgba(96, 165, 250, 0.2);
            padding: 15px 30px;
            border-radius: 15px;
            font-size: 28px;
        }
        
        /* Footer */
        .footer {
            background: rgba(0, 0, 0, 0.3);
            padding: 20px 60px;
            display: flex;
            justify-content: space-between;
            font-size: 24px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo e($conference['name']); ?></h1>
        <div class="time" id="clock">--:--</div>
    </div>
    
    <div class="main-content">
        <?php if ($activeBlock): ?>
            <!-- Active Block with Countdown -->
            <div class="current-section">
                <div class="current-label">🎬 NOW ON STAGE</div>
                <div class="current-title"><?php echo e($activeBlock['title']); ?></div>
                <?php if ($activeBlock['responsible_person']): ?>
                    <div class="current-person"><?php echo e($activeBlock['responsible_person']); ?></div>
                <?php endif; ?>
                
                <?php if ($countdown && !$countdown['finished']): ?>
                    <div class="countdown" id="countdown" data-remaining="<?php echo $countdown['remaining_seconds']; ?>">
                        <?php echo $countdown['remaining_formatted']; ?>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress" style="width: <?php echo $countdown['progress_percent']; ?>"></div>
                    </div>
                <?php else: ?>
                    <div class="countdown">00:00</div>
                <?php endif; ?>
                
                <?php if ($activeBlock['tech_requirements']): ?>
                    <div class="tech-indicators">
                        <?php if ($activeBlock['tech_requirements']['microphone']): ?>
                            <div class="tech-item">🎤 Mic</div>
                        <?php endif; ?>
                        <?php if ($activeBlock['tech_requirements']['presentation']): ?>
                            <div class="tech-item">📊 Slides</div>
                        <?php endif; ?>
                        <?php if ($activeBlock['tech_requirements']['video']): ?>
                            <div class="tech-item">🎬 Video</div>
                        <?php endif; ?>
                        <?php if ($activeBlock['tech_requirements']['lighting']): ?>
                            <div class="tech-item">💡 Lights</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Waiting State -->
            <div class="waiting-state">
                <div class="waiting-icon">⏳</div>
                <div class="waiting-text">Waiting to start...</div>
                <?php if (!empty($upcomingBlocks)): ?>
                    <div class="next-up">
                        Next: <?php echo e($upcomingBlocks[0]['title']); ?> at <?php echo substr($upcomingBlocks[0]['start_time'], 0, 5); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Upcoming -->
        <div class="upcoming-section">
            <div class="upcoming-title">📋 Coming Up</div>
            
            <?php 
            $displayed = 0;
            foreach ($upcomingBlocks as $block): 
                if ($block['status'] !== 'active' && $displayed < 3):
                    $displayed++;
            ?>
                <div class="upcoming-item">
                    <div class="upcoming-time"><?php echo substr($block['start_time'], 0, 5); ?> (<?php echo $block['duration_minutes']; ?> min)</div>
                    <div class="upcoming-item-title"><?php echo e($block['title']); ?></div>
                    <?php if ($block['responsible_person']): ?>
                        <div class="upcoming-person"><?php echo e($block['responsible_person']); ?></div>
                    <?php endif; ?>
                </div>
            <?php 
                endif;
            endforeach; 
            ?>
            
            <?php if ($displayed === 0): ?>
                <div class="upcoming-item">
                    <div class="upcoming-item-title">No more items today</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="footer">
        <span>Day <?php echo $dayNumber; ?></span>
        <span>Press F11 for fullscreen</span>
    </div>
    
    <script>
        // Update clock
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            document.getElementById('clock').textContent = `${hours}:${minutes}`;
        }
        updateClock();
        setInterval(updateClock, 1000);
        
        // Countdown timer
        const countdownEl = document.getElementById('countdown');
        if (countdownEl) {
            let remaining = parseInt(countdownEl.dataset.remaining);
            
            function updateCountdown() {
                if (remaining <= 0) {
                    countdownEl.textContent = '00:00';
                    countdownEl.classList.add('danger');
                    return;
                }
                
                remaining--;
                
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                countdownEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                
                // Update colors based on time
                countdownEl.classList.remove('warning', 'danger');
                if (remaining < 60) {
                    countdownEl.classList.add('danger');
                } else if (remaining < 300) {
                    countdownEl.classList.add('warning');
                }
                
                // Update progress bar
                const progressEl = document.getElementById('progress');
                if (progressEl) {
                    // This is approximate since we don't have total duration in JS
                    // A full refresh would get accurate progress
                }
            }
            
            setInterval(updateCountdown, 1000);
        }
        
        // Auto-refresh every 30 seconds to get updates from server
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
