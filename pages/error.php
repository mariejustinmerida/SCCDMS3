<?php
$error_code = isset($_GET['code']) ? $_GET['code'] : 404;
$error_messages = [
    404 => 'Page Not Found',
    403 => 'Access Forbidden',
    500 => 'Internal Server Error'
];
$error_message = isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'Unknown Error';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?php echo $error_code; ?> - SCC DMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="bg-green-800 p-4 text-center">
            <img src="../assets/images/logo.png" alt="SCC Logo" class="h-20 mx-auto">
            <h2 class="text-white text-xl font-bold mt-2">Document Management System</h2>
        </div>
        
        <div class="p-6 text-center">
            <div class="text-6xl font-bold text-red-500 mb-4"><?php echo $error_code; ?></div>
            <h1 class="text-2xl font-bold mb-4"><?php echo $error_message; ?></h1>
            <p class="text-gray-600 mb-6">Sorry, the page you are looking for could not be found or you don't have permission to access it.</p>
            <a href="../index.php" class="bg-green-800 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                Return to Home
            </a>
        </div>
    </div>
</body>
</html> 