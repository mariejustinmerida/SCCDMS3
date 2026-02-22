<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';

// Check if user is the President
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'President') {
    $_SESSION['setting_error'] = "Access Denied: You do not have permission to perform this action.";
    header("Location: ../pages/dashboard.php?page=settings");
    exit();
}

if (isset($_POST['update_login_bg'])) {
    if (isset($_FILES['login_background']) && $_FILES['login_background']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png'];
        $file_type = $_FILES['login_background']['type'];

        if (in_array($file_type, $allowed_types)) {
            $upload_dir = '../assets/images/';
            $upload_file = $upload_dir . 'login_background.jpg'; // Always use the same name

            // Attempt to move the uploaded file
            if (move_uploaded_file($_FILES['login_background']['tmp_name'], $upload_file)) {
                $_SESSION['setting_success'] = "Login background updated successfully.";
            } else {
                $_SESSION['setting_error'] = "Failed to upload the file. Please check folder permissions.";
            }
        } else {
            $_SESSION['setting_error'] = "Invalid file type. Please upload a JPG or PNG image.";
        }
    } else {
        $_SESSION['setting_error'] = "No file was uploaded or an error occurred during upload.";
    }
}

header("Location: ../pages/dashboard.php?page=settings");
exit(); 