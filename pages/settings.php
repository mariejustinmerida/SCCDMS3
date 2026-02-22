<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure only the President or Super Admin can access this page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['President','Super Admin'])) {
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>
            <strong class='font-bold'>Access Denied!</strong>
            <span class='block sm:inline'>You do not have permission to view this page.</span>
          </div>";
    exit();
}
?>

<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-bold mb-6">Settings</h2>

    <?php if (isset($_SESSION['setting_success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $_SESSION['setting_success']; ?>
        </div>
        <?php unset($_SESSION['setting_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['setting_error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $_SESSION['setting_error']; ?>
        </div>
        <?php unset($_SESSION['setting_error']); ?>
    <?php endif; ?>

    <div class="border-t pt-6">
        <h3 class="text-lg font-medium mb-3">Login Page Background</h3>
        <p class="text-gray-600 mb-4">Upload a new background image for the login page. The recommended size is 1920x1080.</p>
        
        <form action="../actions/update_settings.php" method="POST" enctype="multipart/form-data">
            <div class="flex flex-col space-y-4">
                <div class="flex items-center gap-4">
                    <input type="file" name="login_background" id="login_background" accept="image/jpeg, image/png" class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="flex justify-end">
                    <button type="submit" name="update_login_bg" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Upload Image
                    </button>
                </div>
            </div>
        </form>

        <div class="mt-6">
            <h4 class="font-medium mb-2">Current Background</h4>
            <?php 
            $bg_path = '../assets/images/login_background.jpg';
            if (file_exists($bg_path)): ?>
                <img src="<?php echo $bg_path . '?t=' . time(); ?>" alt="Login Background" class="max-w-sm rounded-lg shadow-md">
            <?php else: ?>
                 <p class="text-gray-500">No custom background set. The default background is being used.</p>
                 <img src="../assets/images/back.jpg" alt="Default Login Background" class="max-w-sm rounded-lg shadow-md">
            <?php endif; ?>
        </div>
    </div>
</div> 