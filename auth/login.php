<?php
// Start output buffering to avoid "headers already sent" issues
if (!headers_sent()) { ob_start(); }

// Disable display_errors in production to prevent output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once '../includes/config.php';
require_once '../includes/security_events.php';
require_once '../includes/logging.php'; // Include logging functions

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../pages/dashboard.php");
    exit;
}

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Both fields are required.";
        // Log failed attempt (missing fields)
        log_failed_login_attempt($email, $_SERVER['REMOTE_ADDR'] ?? '');
    } else {
        $sql = "SELECT u.*, r.role_name, o.office_name FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                JOIN offices o ON u.office_id = o.office_id 
                WHERE email = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role_name'];
                        $_SESSION['profile_image'] = $user['profile_image'];
                        $_SESSION['office_id'] = $user['office_id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['welcome_message'] = true;
                        
                        // Log user login action with enhanced details
                        $details = "User logged in from " . $user['office_name'] . " office";
                        log_user_action(
                            $user['user_id'],
                            'login',
                            $details,
                            null,
                            null,
                            $user['office_id']
                        );
                        
                        header("Location: ../pages/dashboard.php");
                        exit();
                    } else {
                        $error = "Invalid email or password.";
                        // Log failed attempt (bad password)
                        log_failed_login_attempt($email, $_SERVER['REMOTE_ADDR'] ?? '', 'bad_password');
                    }
                } else {
                    $error = "Invalid email or password.";
                    // Log failed attempt (no user)
                    log_failed_login_attempt($email, $_SERVER['REMOTE_ADDR'] ?? '', 'unknown_email');
                }
            } else {
                $error = "Something went wrong. Please try again later.";
            }
            $stmt->close();
        } else {
            $error = "Database error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SCC DMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .login-section {
            background: linear-gradient(180deg,rgb(2, 102, 52) 0%,rgb(1, 34, 18) 100%);
            width: 25%;
        }
        .right-side {
            <?php
            $custom_bg = '../assets/images/login_background.jpg';
            $default_bg = '../assets/images/back.jpg';
            $bg_image = file_exists($custom_bg) ? $custom_bg : $default_bg;
            ?>
            background-image: url('<?php echo $bg_image; ?>');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="flex min-h-screen">
        <!-- Left side -->
        <div class="login-section flex flex-col items-center justify-center p-6 space-y-8">
            <div class="text-center">
                <img src="../assets/images/logo.png" alt="SCC Logo" class="w-34 h-34 mb-5 mx-auto object-contain">
                <h1 class="text-2xl font- text-white">PANAGDAIT</h1>
                <p class="text-white/80">Welcome back to SCC DMS</p>
            </div>

            <div class="w-full max-w-sm">
                <?php if (isset($error)): ?>
                    <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-2 rounded-lg text-sm text-center">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-3">
                    <div>
                        <input type="email" name="email" required placeholder="Email Address"
                            class="w-full px-4 py-3 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white/20">
                    </div>

                    <div class="relative mb-5">
                        <input type="password" name="password" id="password" required placeholder="Password"
                            class="w-full px-4 pr-12 py-3 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white/20">
                        <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none flex items-center justify-center h-full">
                            <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            <svg id="eyeSlashIcon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                            </svg>
                        </button>
                    </div>

                    <div>
                        <button type="submit" class="w-full bg-yellow-400 text-black font-semibold py-3 rounded-lg hover:bg-yellow-500 transition-colors">
                            Sign In
                        </button>
                    </div>
                </form>
                
                
            </div>
        </div>

        <!-- Right side with background image -->
        <div class="flex-1 right-side"></div>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeSlashIcon = document.getElementById('eyeSlashIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeSlashIcon.classList.remove('hidden');
            } else {
                passwordField.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeSlashIcon.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
