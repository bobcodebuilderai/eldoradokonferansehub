<?php
/**
 * Run of Show (Kjøreplan) Management Page
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/runofshow.php';
require_once __DIR__ . '/../includes/comments.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    redirect('/login.php');
}

$conferenceId = intval($_GET['conference_id'] ?? 0);
if (!$conferenceId) {
    setFlashMessage('error', 'Conference ID required');
    redirect('/admin/dashboard.php');
}

if (!canAccessConference($conferenceId)) {
    setFlashMessage('error', 'Access denied');
    redirect('/admin/dashboard.php');
}

$db = getDB();

// Get conference
$stmt = $db->prepare("SELECT * FROM conferences WHERE id = ?");
$stmt->execute([$conferenceId]);
$conference = $stmt->fetch();

if (!$conference) {
    setFlashMessage('error', 'Conference not found');
    redirect('/admin/dashboard.php');
}

// Get current day
$currentDay = intval($_GET['day'] ?? 1);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $data = [
            'conference_id' => $conferenceId,
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'block_type' => $_POST['block_type'] ?? 'presentation',
            'start_time' => $_POST['start_time'] ?? '09:00',
            'duration_minutes' => intval($_POST['duration_minutes'] ?? 30),
            'day_number' => $currentDay,
            'location' => trim($_POST['location'] ?? ''),
            'responsible_person' => trim($_POST['responsible_person'] ?? ''),
            'tech_requirements' => [
                'microphone' => isset($_POST['tech_microphone']),
                'presentation' => isset($_POST['tech_presentation']),
                'video' => isset($_POST['tech_video']),
                'lighting' => isset($_POST['tech_lighting']),
                'audience_interaction' => isset($_POST['tech_audience'])
            ],
            'presenter_notes' => trim($_POST['presenter_notes'] ?? '')
        ];
        
        $result = createROSBlock($data);
        if ($result['success']) {
            setFlashMessage('success', 'Block added successfully.');
        } else {
            setFlashMessage('error', $result['message']);
        }
    } elseif ($action === 'update') {
        $blockId = intval($_POST['block_id'] ?? 0);
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'block_type' => $_POST['block_type'] ?? 'presentation',
            'start_time' => $_POST['start_time'] ?? '09:00',
            'duration_minutes' => intval($_POST['duration_minutes'] ?? 30),
            'location' => trim($_POST['location'] ?? ''),
            'responsible_person' => trim($_POST['responsible_person'] ?? ''),
            'tech_requirements' => [
                'microphone' => isset($_POST['tech_microphone']),
                'presentation' => isset($_POST['tech_presentation']),
                'video' => isset($_POST['tech_video']),
                'lighting' => isset($_POST['tech_lighting']),
                'audience_interaction' => isset($_POST['tech_audience'])
            ],
            'presenter_notes' => trim($_POST['presenter_notes'] ?? ''),
            'venue_notes' => trim($_POST['venue_notes'] ?? '')
        ];
        
        $result = updateROSBlock($blockId, $data);
        if ($result['success']) {
            setFlashMessage('success', 'Block updated.');
        } else {
            setFlashMessage('error', $result['message']);
        }
    } elseif ($action === 'delete') {
        $blockId = intval($_POST['block_id'] ?? 0);
        $result = deleteROSBlock($blockId);
        if ($result['success']) {
            setFlashMessage('success', 'Block deleted.');
        }
    } elseif ($action === 'reorder') {
        $order = json_decode($_POST['order'] ?? '[]', true);
        if ($order) {
            reorderROSBlocks($conferenceId, $currentDay, $order);
        }
        exit; // AJAX response
    } elseif ($action === 'set_status') {
        $blockId = intval($_POST['block_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $block = getROSBlock($blockId);
        setBlockStatus($blockId, $status);
        
        // Notify venue team of important status changes
        if (in_array($status, ['active', 'completed'])) {
            $statusText = $status === 'active' ? 'STARTED' : 'COMPLETED';
            notifyVenueTeam(
                $conferenceId,
                "Run of Show Update: {$block['title']} {$statusText}",
                "Block '{$block['title']}' has {$statusText} in the conference schedule.",
                'normal'
            );
        }
        
        setFlashMessage('success', 'Status updated.');
    } elseif ($action === 'add_comment') {
        $blockId = intval($_POST['block_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $commentType = $_POST['comment_type'] ?? 'general';
        
        if ($comment) {
            addBlockComment($blockId, $_SESSION['user_id'], $comment, $commentType);
            setFlashMessage('success', 'Comment added.');
        }
    } elseif ($action === 'delete_comment') {
        $commentId = intval($_POST['comment_id'] ?? 0);
        deleteBlockComment($commentId, $_SESSION['user_id']);
        setFlashMessage('success', 'Comment deleted.');
    }
    
    redirect('/admin/runofshow.php?conference_id=' . $conferenceId . '&day=' . $currentDay);
}

// Get blocks
$blocks = getROSBlocks($conferenceId, $currentDay);
$dayDuration = calculateDayDuration($conferenceId, $currentDay);

// Get color codes
$typeColors = getBlockTypeColors();

$pageTitle = 'Run of Show - ' . $conference['name'];
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold"><?php echo e($conference['name']); ?></h1>
                    <p class="text-blue-200 mt-1">Run of Show • Day <?php echo $currentDay; ?> • Total: <?php echo $dayDuration['formatted']; ?></p>
                </div>
                <div class="flex gap-3">
                    <a href="combined-dashboard.php?conference_id=<?php echo $conferenceId; ?>" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition">
                        ← Dashboard
                    </a>
                    <a href="../api/export.php?conference_id=<?php echo $conferenceId; ?>&day=<?php echo $currentDay; ?>&format=csv" 
                       class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition"
                       download>
                        📊 Export CSV
                    </a>
                    <a href="../api/export.php?conference_id=<?php echo $conferenceId; ?>&day=<?php echo $currentDay; ?>&format=print" 
                       target="_blank"
                       class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition">
                        🖨️ Print/PDF
                    </a>
                    <a href="/overlay/runofshow-display.php?conference=<?php echo e($conference['uuid']); ?>&day=<?php echo $currentDay; ?>" target="_blank" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition">
                        📺 Stage Display
                    </a>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php echo showFlashMessage(); ?>
        
        <!-- Day Selector -->
        <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
            <div class="flex items-center gap-4">
                <span class="font-medium">Day:</span>
                <div class="flex gap-2">
                    <?php for ($d = 1; $d <= 5; $d++): ?>
                        <a href="?conference_id=<?php echo $conferenceId; ?>&day=<?php echo $d; ?>" 
                           class="px-4 py-2 rounded-lg transition <?php echo $currentDay === $d ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200'; ?>">
                            Day <?php echo $d; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Timeline -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-blue-600 text-white px-6 py-4 flex justify-between items-center">
                        <h2 class="text-xl font-bold">📋 Timeline</h2>
                        <span class="text-blue-200 text-sm">Drag to reorder</span>
                    </div>
                    
                    <div id="blocks-container" class="divide-y divide-gray-200">
                        <?php if (empty($blocks)): ?>
                            <div class="p-8 text-center text-gray-500">
                                <div class="text-4xl mb-2">📅</div>
                                <p>No blocks scheduled for this day.</p>
                                <p class="text-sm">Add your first block using the form →</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($blocks as $block): ?>
                                <div class="block-item p-4 hover:bg-gray-50 transition cursor-move" data-id="<?php echo $block['id']; ?>">
                                    <div class="flex gap-4">
                                        <!-- Time -->
                                        <div class="flex-shrink-0 w-24 text-center">
                                            <div class="font-bold text-lg"><?php echo substr($block['start_time'], 0, 5); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo $block['duration_minutes']; ?> min</div>
                                            <div class="text-xs text-gray-400">→ <?php echo substr($block['end_time'], 0, 5); ?></div>
                                        </div>
                                        
                                        <!-- Content -->
                                        <div class="flex-1">
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="w-3 h-3 rounded-full" style="background-color: <?php echo $block['color_code'] ?: $typeColors[$block['block_type']]; ?>"></span>
                                                        <span class="text-xs font-medium uppercase tracking-wide text-gray-500"><?php echo getBlockTypeLabel($block['block_type']); ?></span>
                                                        <?php if ($block['status'] === 'active'): ?>
                                                            <span class="bg-green-500 text-white text-xs px-2 py-0.5 rounded animate-pulse">LIVE</span>
                                                        <?php elseif ($block['status'] === 'completed'): ?>
                                                            <span class="bg-gray-400 text-white text-xs px-2 py-0.5 rounded">✓ Done</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <h3 class="font-bold text-lg"><?php echo e($block['title']); ?></h3>
                                                    <?php if ($block['description']): ?>
                                                        <p class="text-gray-600 text-sm mt-1"><?php echo e($block['description']); ?></p>
                                                    <?php endif; ?>
                                                    <div class="flex flex-wrap gap-2 mt-2 text-sm">
                                                        <?php if ($block['location']): ?>
                                                            <span class="text-gray-500">📍 <?php echo e($block['location']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($block['responsible_person']): ?>
                                                            <span class="text-gray-500">👤 <?php echo e($block['responsible_person']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <!-- Tech indicators -->
                                                    <?php if ($block['tech_requirements']): ?>
                                                        <div class="flex flex-wrap gap-1 mt-2">
                                                            <?php if ($block['tech_requirements']['microphone']): ?>
                                                                <span class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded">🎤 Mic</span>
                                                            <?php endif; ?>
                                                            <?php if ($block['tech_requirements']['presentation']): ?>
                                                                <span class="bg-purple-100 text-purple-700 text-xs px-2 py-1 rounded">📊 Pres</span>
                                                            <?php endif; ?>
                                                            <?php if ($block['tech_requirements']['video']): ?>
                                                                <span class="bg-red-100 text-red-700 text-xs px-2 py-1 rounded">🎬 Video</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Actions -->
                                                <div class="flex items-center gap-2">
                                                    <?php if ($block['status'] === 'pending'): ?>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="action" value="set_status">
                                                            <input type="hidden" name="block_id" value="<?php echo $block['id']; ?>">
                                                            <input type="hidden" name="status" value="active">
                                                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">▶ Start</button>
                                                        </form>
                                                    <?php elseif ($block['status'] === 'active'): ?>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="action" value="set_status">
                                                            <input type="hidden" name="block_id" value="<?php echo $block['id']; ?>">
                                                            <input type="hidden" name="status" value="completed">
                                                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">✓ Complete</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button onclick="toggleComments(<?php echo $block['id']; ?>)" class="text-gray-600 hover:text-gray-800 text-sm">
                                                        💬 Comments
                                                        <?php 
                                                        $commentCount = count(getBlockComments($block['id']));
                                                        if ($commentCount > 0): ?>
                                                            <span class="bg-blue-500 text-white text-xs px-1.5 py-0.5 rounded-full ml-1"><?php echo $commentCount; ?></span>
                                                        <?php endif; ?>
                                                    </button>
                                                    <button onclick="editBlock(<?php echo htmlspecialchars(json_encode($block)); ?>)" class="text-blue-600 hover:text-blue-800 text-sm">Edit</button>
                                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this block?')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="block_id" value="<?php echo $block['id']; ?>">
                                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Comments Section (Hidden by default) -->
                                    <div id="comments-<?php echo $block['id']; ?>" class="hidden mt-4 ml-28 bg-gray-50 rounded-lg p-4">
                                        <h4 class="font-medium text-gray-700 mb-3">Comments</h4>
                                        
                                        <!-- Existing Comments -->
                                        <div class="space-y-3 mb-4 max-h-60 overflow-y-auto">
                                            <?php 
                                            $comments = getBlockComments($block['id']);
                                            foreach ($comments as $comment): 
                                                $formatted = formatComment($comment);
                                            ?>
                                                <div class="bg-white rounded-lg p-3 shadow-sm">
                                                    <div class="flex justify-between items-start mb-1">
                                                        <span class="font-medium text-sm"><?php echo $formatted['author']; ?> <?php echo $formatted['type_badge']; ?></span>
                                                        <span class="text-xs text-gray-500"><?php echo $formatted['time']; ?></span>
                                                    </div>
                                                    <p class="text-gray-700 text-sm"><?php echo $formatted['text']; ?></p>
                                                    <?php if ($comment['user_id'] == $_SESSION['user_id'] || isVenueAdmin()): ?>
                                                        <form method="POST" class="mt-2">
                                                            <input type="hidden" name="action" value="delete_comment">
                                                            <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                                            <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (empty($comments)): ?>
                                                <p class="text-gray-400 text-sm italic">No comments yet</p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Add Comment Form -->
                                        <form method="POST" class="mt-3">
                                            <input type="hidden" name="action" value="add_comment">
                                            <input type="hidden" name="block_id" value="<?php echo $block['id']; ?>">
                                            <div class="flex gap-2">
                                                <select name="comment_type" class="border rounded-lg px-2 py-1 text-sm">
                                                    <option value="general">General</option>
                                                    <option value="technical">Technical</option>
                                                    <option value="urgent">Urgent</option>
                                                    <option value="presenter">Presenter</option>
                                                </select>
                                                <input type="text" name="comment" placeholder="Add a comment..." required 
                                                       class="flex-1 border rounded-lg px-3 py-1 text-sm">
                                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">Post</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Add/Edit Form -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden sticky top-4">
                    <div class="bg-purple-600 text-white px-6 py-4">
                        <h2 class="text-xl font-bold" id="form-title">➕ Add Block</h2>
                    </div>
                    
                    <div class="p-6">
                        <form method="POST" action="" id="block-form" class="space-y-4">
                            <input type="hidden" name="action" value="create" id="form-action">
                            <input type="hidden" name="block_id" value="" id="block-id">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                <select name="block_type" id="block_type" class="w-full border rounded-lg px-3 py-2" onchange="updateColorPreview()">
                                    <option value="presentation">Innlegg</option>
                                    <option value="break">Pause</option>
                                    <option value="video">Video</option>
                                    <option value="audio">Lyd</option>
                                    <option value="other">Annet</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                                <input type="text" name="title" id="title" required class="w-full border rounded-lg px-3 py-2" placeholder="e.g., Opening Keynote">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="description" id="description" rows="2" class="w-full border rounded-lg px-3 py-2" placeholder="Brief description..."></textarea>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                                    <input type="time" name="start_time" id="start_time" required class="w-full border rounded-lg px-3 py-2" value="09:00">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Duration (min)</label>
                                    <input type="number" name="duration_minutes" id="duration_minutes" required min="5" step="5" class="w-full border rounded-lg px-3 py-2" value="30">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Location/Room</label>
                                <input type="text" name="location" id="location" class="w-full border rounded-lg px-3 py-2" placeholder="e.g., Main Stage">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Responsible Person</label>
                                <input type="text" name="responsible_person" id="responsible_person" class="w-full border rounded-lg px-3 py-2" placeholder="e.g., John Smith">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Technical Requirements</label>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="tech_microphone" id="tech_microphone" class="mr-2">
                                        <span class="text-sm">🎤 Microphone</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="tech_presentation" id="tech_presentation" class="mr-2">
                                        <span class="text-sm">📊 Presentation</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="tech_video" id="tech_video" class="mr-2">
                                        <span class="text-sm">🎬 Video</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="tech_lighting" id="tech_lighting" class="mr-2">
                                        <span class="text-sm">💡 Lighting</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="tech_audience" id="tech_audience" class="mr-2">
                                        <span class="text-sm">👥 Audience Interaction</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Presenter Notes</label>
                                <textarea name="presenter_notes" id="presenter_notes" rows="2" class="w-full border rounded-lg px-3 py-2" placeholder="Notes for presenter..."></textarea>
                            </div>
                            
                            <div class="flex gap-2">
                                <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 rounded-lg transition">
                                    Save Block
                                </button>
                                <button type="button" onclick="resetForm()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Drag and drop functionality
let draggedElement = null;

const container = document.getElementById('blocks-container');

container.addEventListener('dragstart', (e) => {
    if (e.target.classList.contains('block-item')) {
        draggedElement = e.target;
        e.target.style.opacity = '0.5';
    }
});

container.addEventListener('dragend', (e) => {
    if (e.target.classList.contains('block-item')) {
        e.target.style.opacity = '1';
        draggedElement = null;
    }
});

container.addEventListener('dragover', (e) => {
    e.preventDefault();
});

container.addEventListener('drop', (e) => {
    e.preventDefault();
    if (!draggedElement) return;
    
    const afterElement = getDragAfterElement(container, e.clientY);
    if (afterElement) {
        container.insertBefore(draggedElement, afterElement);
    } else {
        container.appendChild(draggedElement);
    }
    
    // Save new order
    saveOrder();
});

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.block-item:not([style*="opacity: 0.5"])')];
    
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        }
        return closest;
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

function saveOrder() {
    const items = [...container.querySelectorAll('.block-item')];
    const order = items.map(item => parseInt(item.dataset.id));
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=reorder&order=${encodeURIComponent(JSON.stringify(order))}`
    });
}

// Edit block
function editBlock(block) {
    document.getElementById('form-title').textContent = '✏️ Edit Block';
    document.getElementById('form-action').value = 'update';
    document.getElementById('block-id').value = block.id;
    document.getElementById('block_type').value = block.block_type;
    document.getElementById('title').value = block.title;
    document.getElementById('description').value = block.description;
    document.getElementById('start_time').value = block.start_time;
    document.getElementById('duration_minutes').value = block.duration_minutes;
    document.getElementById('location').value = block.location;
    document.getElementById('responsible_person').value = block.responsible_person;
    document.getElementById('presenter_notes').value = block.presenter_notes || '';
    
    // Tech requirements
    if (block.tech_requirements) {
        document.getElementById('tech_microphone').checked = block.tech_requirements.microphone || false;
        document.getElementById('tech_presentation').checked = block.tech_requirements.presentation || false;
        document.getElementById('tech_video').checked = block.tech_requirements.video || false;
        document.getElementById('tech_lighting').checked = block.tech_requirements.lighting || false;
        document.getElementById('tech_audience').checked = block.tech_requirements.audience_interaction || false;
    }
    
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('form-title').textContent = '➕ Add Block';
    document.getElementById('form-action').value = 'create';
    document.getElementById('block-id').value = '';
    document.getElementById('block-form').reset();
    document.getElementById('start_time').value = '09:00';
    document.getElementById('duration_minutes').value = '30';
}

// Make items draggable
document.querySelectorAll('.block-item').forEach(item => {
    item.draggable = true;
});

// Toggle comments visibility
function toggleComments(blockId) {
    const commentsSection = document.getElementById('comments-' + blockId);
    if (commentsSection) {
        commentsSection.classList.toggle('hidden');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
