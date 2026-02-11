<?php
/**
 * User Profile & Notification Settings
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    redirect('/login.php');
}

$userId = $_SESSION['user_id'];
$db = getDB();

// Get current user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Update phone
        updateUserPhone($userId, $phone);
        
        // Update email
        $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$email, $userId]);
        
        setFlashMessage('success', 'Profile updated.');
    } elseif ($action === 'update_notifications') {
        $data = [
            'notification_method' => $_POST['notification_method'] ?? 'email',
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0
        ];
        
        updateNotificationPreferences($userId, $data);
        setFlashMessage('success', 'Notification preferences updated.');
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($newPassword !== $confirmPassword) {
            setFlashMessage('error', 'New passwords do not match.');
        } elseif (strlen($newPassword) < 6) {
            setFlashMessage('error', 'Password must be at least 6 characters.');
        } else {
            // Verify current password
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            
            if (password_verify($currentPassword, $result['password_hash'])) {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$newHash, $userId]);
                setFlashMessage('success', 'Password changed.');
            } else {
                setFlashMessage('error', 'Current password is incorrect.');
            }
        }
    } elseif ($action === 'test_notification') {
        $method = $_POST['test_method'] ?? 'email';
        
        if ($method === 'email' && $user['email']) {
            $result = sendEmail(
                $user['email'],
                'Test Notification - Eldorado Konferansehub',
                '<h2>Test Notification</h2><p>This is a test email from Eldorado Konferansehub.</p>'
            );
            if ($result['success']) {
                setFlashMessage('success', 'Test email sent to ' . $user['email']);
            } else {
                setFlashMessage('error', 'Failed to send email: ' . $result['message']);
            }
        } elseif ($method === 'sms' && $user['phone']) {
            require_once __DIR__ . '/../includes/sms.php';
            $result = sendSMS($user['phone'], 'Test SMS from Eldorado Konferansehub');
            if ($result['success']) {
                setFlashMessage('success', 'Test SMS sent to ' . $user['phone']);
            } else {
                setFlashMessage('error', 'Failed to send SMS: ' . $result['message']);
            }
        }
    }
    
    redirect('/admin/profile.php');
}

// Get notification preferences
$preferences = getNotificationPreferences($userId);
$pageTitle = 'My Profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold">👤 My Profile</h1>
            <p class="text-blue-200 mt-1">Manage your account and notification settings</p>
        </div>
    </div>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php echo showFlashMessage(); ?>
        
        <div class="space-y-8">
            <!-- Profile Information -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-bold">Profile Information</h2>
                </div>
                
                <div class="p-6">
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" value="<?php echo e($user['username']); ?>" disabled 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100">
                            <p class="text-xs text-gray-500 mt-1">Username cannot be changed</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input type="email" name="email" value="<?php echo e($user['email']); ?>" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo e($user['phone'] ?? ''); ?>" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="+47 999 99 999">
                            <p class="text-xs text-gray-500 mt-1">Required for SMS notifications. Include country code (e.g., +47).</p>
                        </div>
                        
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                            Save Profile
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Notification Preferences -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-bold">🔔 Notification Preferences</h2>
                </div>
                
                <div class="p-6">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_notifications">
                        
                        <!-- Primary Method -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Preferred Notification Method</label>
                            <div class="space-y-2">
                                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input type="radio" name="notification_method" value="email" 
                                           <?php echo ($preferences['notification_method'] ?? 'email') === 'email' ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600">
                                    <span class="ml-3">
                                        <span class="font-medium">📧 Email Only</span>
                                        <span class="text-gray-500 text-sm block">Receive notifications via email</span>
                                    </span>
                                </label>
                                
                                <?php if ($user['phone']): ?>
                                    <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="notification_method" value="sms" 
                                               <?php echo ($preferences['notification_method'] ?? '') === 'sms' ? 'checked' : ''; ?>
                                               class="h-4 w-4 text-blue-600">
                                        <span class="ml-3">
                                            <span class="font-medium">📱 SMS Only</span>
                                            <span class="text-gray-500 text-sm block">Receive notifications via SMS</span>
                                        </span>
                                    </label>
                                    
                                    <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="notification_method" value="both" 
                                               <?php echo ($preferences['notification_method'] ?? '') === 'both' ? 'checked' : ''; ?>
                                               class="h-4 w-4 text-blue-600">
                                        <span class="ml-3">
                                            <span class="font-medium">📧📱 Both Email and SMS</span>
                                            <span class="text-gray-500 text-sm block">Receive notifications via both methods</span>
                                        </span>
                                    </label>
                                <?php else: ?>
                                    <p class="text-sm text-orange-600 ml-6">Add a phone number to enable SMS notifications</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Toggle Switches -->
                        <div class="border-t pt-4">
                            <label class="flex items-center justify-between py-2">
                                <span>
                                    <span class="font-medium">Email Notifications</span>
                                    <span class="text-gray-500 text-sm block">Receive important updates via email</span>
                                </span>
                                <input type="checkbox" name="email_notifications" 
                                       <?php echo ($preferences['email_notifications'] ?? 1) ? 'checked' : ''; ?>
                                       class="h-5 w-5 text-blue-600 rounded">
                            </label>
                            
                            <?php if ($user['phone']): ?>
                                <label class="flex items-center justify-between py-2 border-t">
                                    <span>
                                        <span class="font-medium">SMS Notifications</span>
                                        <span class="text-gray-500 text-sm block">Receive important updates via SMS</span>
                                    </span>
                                    <input type="checkbox" name="sms_notifications" 
                                           <?php echo ($preferences['sms_notifications'] ?? 0) ? 'checked' : ''; ?>
                                           class="h-5 w-5 text-blue-600 rounded">
                                </label>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                            Save Notification Settings
                        </button>
                    </form>
                    
                    <!-- Test Notifications -->
                    <div class="border-t mt-6 pt-6">
                        <h3 class="font-medium mb-3">Test Notifications</h3>
                        <div class="flex gap-3">
                            <?php if ($user['email']): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="test_notification">
                                    <input type="hidden" name="test_method" value="email">
                                    <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg transition">
                                        📧 Test Email
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($user['phone']): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="test_notification">
                                    <input type="hidden" name="test_method" value="sms">
                                    <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg transition">
                                        📱 Test SMS
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-bold">🔐 Change Password</h2>
                </div>
                
                <div class="p-6">
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                            <input type="password" name="current_password" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <input type="password" name="new_password" required minlength="6"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                            <input type="password" name="confirm_password" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                            Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
