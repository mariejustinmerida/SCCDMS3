<?php
session_start();
require_once '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get current user data
$stmt = $conn->prepare("SELECT username, email, profile_image FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update email
    if (isset($_POST['email']) && !empty($_POST['email'])) {
        $new_email = trim($_POST['email']);
        
        // Validate email
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format";
        } else {
            // Check if email already exists for another user
            $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $check_stmt->bind_param("si", $new_email, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Email already in use by another account";
            } else {
                $update_stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
                $update_stmt->bind_param("si", $new_email, $user_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['email'] = $new_email;
                    $success_message = "Email updated successfully";
                } else {
                    $error_message = "Failed to update email: " . $conn->error;
                }
            }
        }
    }
    
    // Update password
    if (isset($_POST['current_password'], $_POST['new_password'], $_POST['confirm_password']) && 
        !empty($_POST['current_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $pwd_stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $pwd_stmt->bind_param("i", $user_id);
        $pwd_stmt->execute();
        $pwd_result = $pwd_stmt->get_result();
        $user_data = $pwd_result->fetch_assoc();
        
        if (password_verify($current_password, $user_data['password'])) {
            // Check if new passwords match
            if ($new_password === $confirm_password) {
                // Validate password strength
                if (strlen($new_password) < 8) {
                    $error_message = "Password must be at least 8 characters long";
                } else {
                    // Hash the new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password in database
                    $update_pwd_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $update_pwd_stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($update_pwd_stmt->execute()) {
                        $success_message = "Password updated successfully";
                    } else {
                        $error_message = "Failed to update password: " . $conn->error;
                    }
                }
            } else {
                $error_message = "New passwords do not match";
            }
        } else {
            $error_message = "Current password is incorrect";
        }
    }
    
    // Update profile picture
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        // Check file type and size
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            $error_message = "Only JPG, PNG and GIF images are allowed";
        } elseif ($_FILES['profile_image']['size'] > $max_size) {
            $error_message = "Image size should be less than 5MB";
        } else {
            try {
                // Create absolute path for upload directory
                $base_dir = realpath(dirname(dirname(__FILE__))); // Get the absolute path to the project root
                
                // Debug information
                error_log("Base directory: " . $base_dir);
                
                // Create storage directory if it doesn't exist
                $storage_dir = $base_dir . DIRECTORY_SEPARATOR . 'storage';
                if (!is_dir($storage_dir)) {
                    if (file_exists($storage_dir)) {
                        // If it exists but is not a directory, rename it
                        rename($storage_dir, $storage_dir . '.bak');
                    }
                    
                    if (!mkdir($storage_dir, 0777)) {
                        throw new Exception("Failed to create storage directory: " . $storage_dir);
                    }
                    chmod($storage_dir, 0777);
                    error_log("Created storage directory: " . $storage_dir);
                }
                
                // Create profiles directory if it doesn't exist
                $profiles_dir = $storage_dir . DIRECTORY_SEPARATOR . 'profiles';
                if (!is_dir($profiles_dir)) {
                    if (file_exists($profiles_dir)) {
                        // If it exists but is not a directory, rename it
                        rename($profiles_dir, $profiles_dir . '.bak');
                    }
                    
                    if (!mkdir($profiles_dir, 0777)) {
                        throw new Exception("Failed to create profiles directory: " . $profiles_dir);
                    }
                    chmod($profiles_dir, 0777);
                    error_log("Created profiles directory: " . $profiles_dir);
                }
                
                $upload_dir = $profiles_dir . DIRECTORY_SEPARATOR;
                error_log("Upload directory: " . $upload_dir);
                
                // Generate unique filename
                $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                $target_file = $upload_dir . $filename;
                
                error_log("Target file: " . $target_file);
                
                // Upload file
                if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                    throw new Exception("Failed to move uploaded file to: " . $target_file);
                }
                
                // Delete old profile image if exists
                if (!empty($user['profile_image'])) {
                    $old_file = $base_dir . DIRECTORY_SEPARATOR . $user['profile_image'];
                    if (file_exists($old_file) && $user['profile_image'] != 'default_profile.png') {
                        unlink($old_file);
                        error_log("Deleted old profile image: " . $old_file);
                    }
                }
                
                // Update database with relative path
                $relative_path = 'storage' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR . $filename;
                $update_img_stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                $update_img_stmt->bind_param("si", $relative_path, $user_id);
                
                if (!$update_img_stmt->execute()) {
                    throw new Exception("Failed to update database: " . $conn->error);
                }
                
                // Update session
                $_SESSION['profile_image'] = $relative_path;
                $success_message = "Profile image updated successfully";
                
            } catch (Exception $e) {
                $error_message = $e->getMessage();
                error_log("Error in update_profile.php: " . $error_message);
            }
        }
    }
    
    // Refresh user data after updates
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - SCC DMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8f9fa;
        }
        .header-bg {
            background: linear-gradient(90deg, rgb(2, 102, 52) 0%, rgb(1, 34, 18) 100%);
        }
        .card {
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .form-input {
            transition: all 0.2s ease;
        }
        .form-input:focus {
            border-color: rgb(2, 102, 52);
            box-shadow: 0 0 0 3px rgba(2, 102, 52, 0.2);
        }
        .btn-primary {
            background-color: rgb(2, 102, 52);
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background-color: rgb(1, 80, 40);
        }
        .profile-image-container {
            position: relative;
            overflow: hidden;
        }
        .profile-image-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 8px 0;
            text-align: center;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .profile-image-container:hover .profile-image-overlay {
            opacity: 1;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="header-bg text-white shadow-md">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <img src="../assets/images/logo.png" alt="SCC Logo" class="w-12 h-12 object-contain">
                    <h1 class="text-white text-2xl font-bold">SCC DMS</h1>
                </div>
                <div class="flex items-center gap-4">
                    <a href="../pages/dashboard.php" class="flex items-center gap-2 bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto py-8 px-4">
        <div class="max-w-4xl mx-auto">
            <!-- Page Title -->
            <div class="mb-8 text-center">
                <h2 class="text-3xl font-bold text-gray-800">My Profile</h2>
                <p class="text-gray-600 mt-2">Manage your personal information and account settings</p>
            </div>

            <!-- Notification Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4 mb-6 flex items-start">
                    <svg class="h-5 w-5 mr-2 mt-0.5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="font-medium"><?php echo $success_message; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 mb-6 flex items-start">
                    <svg class="h-5 w-5 mr-2 mt-0.5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="font-medium"><?php echo $error_message; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Profile Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Profile Picture Card -->
                <div class="card bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="bg-gradient-to-r from-green-600 to-green-800 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white">Profile Picture</h3>
                    </div>
                    <div class="p-6">
                        <form action="" method="POST" enctype="multipart/form-data" class="flex flex-col items-center">
                            <div class="profile-image-container mb-6 rounded-full overflow-hidden border-4 border-white shadow-lg" style="width: 150px; height: 150px;">
                                <?php if(isset($user['profile_image']) && !empty($user['profile_image'])): ?>
                                    <img src="<?php echo '../' . $user['profile_image']; ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gray-300 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <div class="profile-image-overlay">Click to change</div>
                            </div>
                            
                            <div class="w-full">
                                <div class="relative">
                                    <input type="file" name="profile_image" id="profile_image" accept="image/*" class="hidden">
                                    <label for="profile_image" class="block w-full text-center px-4 py-2 border border-gray-300 rounded-lg cursor-pointer bg-white hover:bg-gray-50 transition-colors">
                                        Choose New Image
                                    </label>
                                </div>
                                <p class="mt-2 text-xs text-gray-500 text-center">JPG, PNG or GIF. Max 5MB.</p>
                                <div id="selected-file" class="mt-2 text-sm text-center text-gray-600 hidden"></div>
                                <button type="submit" class="mt-4 w-full btn-primary text-white py-2 px-4 rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                    Update Profile Picture
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Account Information Cards -->
                <div class="md:col-span-2 space-y-6">
                    <!-- Email Card -->
                    <div class="card bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-r from-green-600 to-green-800 px-6 py-4">
                            <h3 class="text-lg font-semibold text-white">Email Address</h3>
                        </div>
                        <div class="p-6">
                            <form action="" method="POST">
                                <div class="mb-4">
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required
                                        class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none">
                                    <p class="mt-2 text-xs text-gray-500">This email will be used for account-related notifications.</p>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn-primary text-white py-2 px-6 rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                        Update Email
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Password Card -->
                    <div class="card bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="bg-gradient-to-r from-green-600 to-green-800 px-6 py-4">
                            <h3 class="text-lg font-semibold text-white">Change Password</h3>
                        </div>
                        <div class="p-6">
                            <form action="" method="POST">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="mb-4 md:col-span-2">
                                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                        <input type="password" id="current_password" name="current_password" required
                                            class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none">
                                    </div>
                                    <div class="mb-4">
                                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                        <input type="password" id="new_password" name="new_password" required
                                            class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none">
                                    </div>
                                    <div class="mb-4">
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" required
                                            class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none">
                                    </div>
                                </div>
                                <p class="mb-4 text-xs text-gray-500">Password must be at least 8 characters long. We recommend using a combination of letters, numbers, and special characters.</p>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn-primary text-white py-2 px-6 rounded-lg font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                        Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t mt-12 py-6">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center mb-4 md:mb-0">
                    <img src="../assets/images/logo.png" alt="SCC Logo" class="w-8 h-8 mr-2">
                    <span class="text-gray-700 font-medium">SCC Document Management System</span>
                </div>
                <div class="text-gray-500 text-sm">
                    &copy; <?php echo date('Y'); ?> St. Catherine College. All rights reserved.
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Show selected filename when user chooses a profile image
        document.getElementById('profile_image').addEventListener('change', function() {
            const fileLabel = document.getElementById('selected-file');
            if (this.files.length > 0) {
                fileLabel.textContent = this.files[0].name;
                fileLabel.classList.remove('hidden');
            } else {
                fileLabel.classList.add('hidden');
            }
        });
    </script>
</body>
</html> 