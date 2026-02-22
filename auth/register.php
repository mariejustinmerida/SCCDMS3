<?php
session_start();
require_once '../includes/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $_POST['full_name'];
    $role_id = $_POST['role_id'];
    $office_id = $_POST['office_id'];
    
    // Handle profile image upload
    $profile_image = null;
    if(isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
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
            $filename = time() . '_' . basename($_FILES["profile_image"]["name"]);
            $target_file = $upload_dir . $filename;
            
            error_log("Target file: " . $target_file);
            
            // Upload file
            if (!move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                throw new Exception("Failed to move uploaded file to: " . $target_file);
            }
            
            // Store relative path in database
            $profile_image = 'storage' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR . $filename;
            error_log("Profile image path: " . $profile_image);
            
        } catch (Exception $e) {
            $error = $e->getMessage();
            error_log("Error in register.php: " . $error);
        }
    }
    
    // Check if all required fields are set before preparing the statement
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($role_id) || empty($office_id)) {
        $error = "All fields are required";
    } else {
        // Check if the prepare statement was successful
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role_id, office_id, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
            error_log("Prepare statement failed: " . $conn->error);
        } else {
            $stmt->bind_param("sssssis", $username, $email, $password, $full_name, $role_id, $office_id, $profile_image);
    
            if ($stmt->execute()) {
                $success = "User registered successfully";
                // Redirect to login page after successful registration
                header("Location: login.php?registered=1");
                exit;
            } else {
                $error = "Error registering user: " . $stmt->error;
                error_log("Execute statement failed: " . $stmt->error);
            }
        }
    }
}

$roles = $conn->query("SELECT * FROM roles");
$offices = $conn->query("SELECT * FROM offices");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register User - SCC DMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: rgb(22, 59, 32); }
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4">
        <div class="form-container rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-yellow-500 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex flex-col items-center justify-center p-6 space-y-4">
                            <div class="text-center">
                                <img src="../assets/images/logo.png" alt="SCC Logo" class="w-16 h-16">
                                <h1 class="text-2xl font-bold text-white">PANAGDAIT</h1>
                                <p class="text-white/80">Register for SCC DMS</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($success)): ?>
                <div class="m-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="m-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="p-6 grid grid-cols-2 gap-6">
                <div class="col-span-2 flex items-center justify-center">
                    <div class="relative group">
                        <div class="w-32 h-32 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden border-4 border-yellow-500">
                            <img id="preview" src="#" alt="" class="w-full h-full object-cover hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <input type="file" name="profile_image" accept="image/*" class="hidden" id="profile_image" onchange="previewImage(this)">
                        <button type="button" onclick="document.getElementById('profile_image').click()" 
                                class="absolute bottom-0 right-0 bg-yellow-500 text-white p-2 rounded-full shadow-lg opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" name="username" required 
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" required 
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" name="password" required 
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" name="full_name" required 
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Role</label>
                    <select name="role_id" required 
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        <?php while ($role = $roles->fetch_assoc()): ?>
                            <option value="<?php echo $role['role_id']; ?>"><?php echo $role['role_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Office</label>
                    <select name="office_id" required 
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        <?php while ($office = $offices->fetch_assoc()): ?>
                            <option value="<?php echo $office['office_id']; ?>"><?php echo $office['office_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-span-2 flex items-center justify-between pt-4">
                    <a href="dashboard.php" class="text-yellow-600 hover:text-yellow-700 font-medium">
                        Back to Dashboard
                    </a>
                    <button type="submit" class="px-6 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2">
                        Register User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('preview');
            const file = input.files[0];
            const reader = new FileReader();

            reader.onloadend = function() {
                preview.src = reader.result;
                preview.classList.remove('hidden');
                preview.previousElementSibling.classList.add('hidden');
            }

            if (file) {
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>
