<?php
// Debug version of login.php to identify the exact issue
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Information</h2>";

// Test database connection
require_once 'includes/config.php';

echo "<h3>Database Connection Test:</h3>";
if ($conn && $conn->connect_error) {
    echo "❌ Database connection failed: " . $conn->connect_error;
} else {
    echo "✅ Database connection successful";
}

// Test session
echo "<h3>Session Test:</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . "<br>";
echo "Session Data: ";
var_dump($_SESSION);

// Test if we can write to session
$_SESSION['test'] = 'test_value';
echo "Session write test: " . (isset($_SESSION['test']) ? '✅ Success' : '❌ Failed') . "<br>";

// Test form processing
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo "<h3>Form Processing Test:</h3>";
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    echo "Email: " . htmlspecialchars($email) . "<br>";
    echo "Password length: " . strlen($password) . "<br>";
    
    if (empty($email) || empty($password)) {
        echo "❌ Empty fields detected";
    } else {
        echo "✅ Form data received";
        
        // Test database query
        $sql = "SELECT u.*, r.role_name, o.office_name FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                JOIN offices o ON u.office_id = o.office_id 
                WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                echo "<br>Query executed successfully. Rows found: " . $result->num_rows;
                
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    echo "<br>✅ User found: " . htmlspecialchars($user['username']);
                    
                    if (password_verify($password, $user['password'])) {
                        echo "<br>✅ Password verified";
                        
                        // Test session variables
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role_name'];
                        $_SESSION['profile_image'] = $user['profile_image'];
                        $_SESSION['office_id'] = $user['office_id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['welcome_message'] = true;
                        
                        echo "<br>✅ Session variables set";
                        echo "<br>Session data after login: ";
                        var_dump($_SESSION);
                        
                        echo "<br><strong>Would redirect to: ../pages/dashboard.php</strong>";
                        echo "<br><a href='../pages/dashboard.php'>Click here to test dashboard access</a>";
                        
                    } else {
                        echo "<br>❌ Password verification failed";
                    }
                } else {
                    echo "<br>❌ No user found with that email";
                }
            } else {
                echo "<br>❌ Query execution failed: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "<br>❌ Statement preparation failed: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Login - SCC DMS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug { background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; }
        form { background: #e8f4f8; padding: 20px; border-radius: 5px; margin: 20px 0; }
        input { margin: 5px; padding: 8px; width: 200px; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="debug">
        <h3>Test Login Form:</h3>
        <form method="POST">
            <div>
                <label>Email:</label><br>
                <input type="email" name="email" required>
            </div>
            <div>
                <label>Password:</label><br>
                <input type="password" name="password" required>
            </div>
            <div>
                <button type="submit">Test Login</button>
            </div>
        </form>
    </div>
</body>
</html>

