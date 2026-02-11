<?php
/**
 * File Management Page for Conference
 * Upload and manage files shared with venue
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/files.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    redirect('/login.php');
}

$conferenceId = intval($_GET['conference_id'] ?? 0);
if (!$conferenceId) {
    setFlashMessage('error', 'Conference ID required');
    redirect('/admin/dashboard.php');
}

// Check access
if (!canAccessConference($conferenceId)) {
    setFlashMessage('error', 'Access denied');
    redirect('/admin/dashboard.php');
}

$db = getDB();

// Get conference details
$stmt = $db->prepare("SELECT * FROM conferences WHERE id = ?");
$stmt->execute([$conferenceId]);
$conference = $stmt->fetch();

if (!$conference) {
    setFlashMessage('error', 'Conference not found');
    redirect('/admin/dashboard.php');
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $description = trim($_POST['description'] ?? '');
    $isVenueVisible = isset($_POST['is_venue_visible']);
    
    $result = uploadConferenceFile(
        $conferenceId,
        $_SESSION['user_id'],
        $_FILES['file'],
        $description,
        $isVenueVisible
    );
    
    if ($result['success']) {
        setFlashMessage('success', 'File uploaded successfully.');
    } else {
        setFlashMessage('error', $result['message']);
    }
    
    redirect('/admin/files.php?conference_id=' . $conferenceId);
}

// Handle file deletion
if (isset($_GET['delete'])) {
    $fileId = intval($_GET['delete']);
    $result = deleteConferenceFile($fileId, $_SESSION['user_id']);
    
    if ($result['success']) {
        setFlashMessage('success', 'File deleted.');
    } else {
        setFlashMessage('error', $result['message']);
    }
    
    redirect('/admin/files.php?conference_id=' . $conferenceId);
}

// Get files
$files = getConferenceFiles($conferenceId, true);

// Group by type
$filesByType = [
    'pdf' => [],
    'image' => [],
    'video' => [],
    'audio' => [],
    'other' => []
];

foreach ($files as $file) {
    $filesByType[$file['file_type']][] = $file;
}

$pageTitle = 'Files - ' . $conference['name'];
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold"><?php echo e($conference['name']); ?></h1>
                    <p class="text-blue-200 mt-1">File Management</p>
                </div>
                <div class="flex gap-3">
                    <a href="combined-dashboard.php?conference_id=<?php echo $conferenceId; ?>" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition">
                        ← Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php echo showFlashMessage(); ?>
        
        <!-- Upload Section -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-8">
            <div class="bg-blue-600 text-white px-6 py-4">
                <h2 class="text-xl font-bold">📁 Upload Files</h2>
                <p class="text-blue-200 text-sm">Share files with venue staff</p>
            </div>
            
            <div class="p-6">
                <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select File</label>
                        <input type="file" name="file" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               accept=".pdf,.jpg,.jpeg,.png,.gif,.mp4,.mov,.mp3,.wav">
                        <p class="text-xs text-gray-500 mt-1">
                            Allowed: PDF, Images, Video, Audio. Max 50MB.
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description (optional)</label>
                        <input type="text" name="description" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="e.g., Speaker presentation slides">
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="is_venue_visible" id="is_venue_visible" checked 
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_venue_visible" class="ml-2 block text-sm text-gray-700">
                            Visible to venue staff
                        </label>
                    </div>
                    
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                        📤 Upload File
                    </button>
                </form>
            </div>
        </div>

        <!-- Files by Type -->
        <div class="space-y-6">
            <?php foreach ($filesByType as $type => $typeFiles): ?>
                <?php if (!empty($typeFiles)): ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-bold flex items-center gap-2">
                                <?php echo getFileIcon($type); ?>
                                <?php echo ucfirst($type); ?> Files
                                <span class="text-sm font-normal text-gray-500">(<?php echo count($typeFiles); ?>)</span>
                            </h3>
                        </div>
                        
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($typeFiles as $file): ?>
                                <div class="p-4 hover:bg-gray-50 transition">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-2xl"><?php echo getFileIcon($file['file_type']); ?></span>
                                                <div>
                                                    <p class="font-semibold text-gray-900">
                                                        <?php echo e($file['original_name']); ?>
                                                    </p>
                                                    <p class="text-sm text-gray-500">
                                                        <?php echo formatFileSize($file['file_size']); ?> • 
                                                        Uploaded by <?php echo e($file['uploaded_by_name']); ?> • 
                                                        <?php echo date('M j, Y', strtotime($file['uploaded_at'])); ?>
                                                    </p>
                                                    <?php if ($file['description']): ?>
                                                        <p class="text-sm text-gray-600 mt-1">
                                                            <?php echo e($file['description']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-2 ml-4">
                                            <?php if ($file['is_venue_visible']): ?>
                                                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Venue visible</span>
                                            <?php else: ?>
                                                <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded">Private</span>
                                            <?php endif; ?>
                                            
                                            <a href="/uploads/<?php echo e($file['file_path']); ?>" 
                                               target="_blank"
                                               class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded text-sm transition">
                                                View
                                            </a>
                                            
                                            <a href="files.php?conference_id=<?php echo $conferenceId; ?>&delete=<?php echo $file['id']; ?>" 
                                               onclick="return confirm('Delete this file?')"
                                               class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded text-sm transition">
                                                Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <?php if (empty($files)): ?>
                <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                    <div class="text-6xl mb-4">📂</div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No files uploaded yet</h3>
                    <p class="text-gray-500">Upload PDFs, images, videos, or audio files to share with the venue.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
