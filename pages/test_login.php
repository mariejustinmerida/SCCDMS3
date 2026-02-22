<?php
// Simple test to identify login issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Login Test</h2>";

// Test 1: Basic PHP
echo "✅ PHP is working<br>";

// Test 2: Session
session_start();
echo "✅ Session started<br>";

// Test 3: Database connection
try {
    require_once 'includes/config.php';
    if ($conn && !$conn->connect_error) {
        echo "✅ Database connected<br>";
    } else {
        echo "❌ Database connection failed: " . ($conn->connect_error ?? 'Unknown error') . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 4: Security events
try {
    require_once 'includes/security_events.php';
    echo "✅ Security events loaded<br>";
} catch (Exception $e) {
    echo "❌ Security events error: " . $e->getMessage() . "<br>";
}

// Test 5: Logging
try {
    require_once 'includes/logging.php';
    echo "✅ Logging loaded<br>";
} catch (Exception $e) {
    echo "❌ Logging error: " . $e->getMessage() . "<br>";
}

// Test 6: Check if user is logged in
if (isset($_SESSION['user_id'])) {
    echo "✅ User is logged in (ID: " . $_SESSION['user_id'] . ")<br>";
    echo "<a href='pages/dashboard.php'>Go to Dashboard</a><br>";
} else {
    echo "ℹ️ User is not logged in<br>";
}

// Test 7: Simple login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    echo "<h3>Login Attempt:</h3>";
    echo "Email: " . htmlspecialchars($email) . "<br>";
    echo "Password length: " . strlen($password) . "<br>";
    
    if (empty($email) || empty($password)) {
        echo "❌ Empty fields<br>";
    } else {
        // Test database query
        $sql = "SELECT u.*, r.role_name, o.office_name FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                JOIN offices o ON u.office_id = o.office_id 
                WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                echo "Query executed. Rows found: " . $result->num_rows . "<br>";
                
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    echo "✅ User found: " . htmlspecialchars($user['username']) . "<br>";
                    
                    if (password_verify($password, $user['password'])) {
                        echo "✅ Password verified<br>";
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role_name'];
                        $_SESSION['profile_image'] = $user['profile_image'];
                        $_SESSION['office_id'] = $user['office_id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['welcome_message'] = true;
                        
                        echo "✅ Session variables set<br>";
                        echo "<strong>Login successful! Redirecting...</strong><br>";
                        echo "<script>setTimeout(() => window.location.href = 'pages/dashboard.php', 2000);</script>";
                        
                    } else {
                        echo "❌ Password verification failed<br>";
                    }
                } else {
                    echo "❌ No user found with that email<br>";
                }
            } else {
                echo "❌ Query execution failed: " . $stmt->error . "<br>";
            }
            $stmt->close();
        } else {
            echo "❌ Statement preparation failed: " . $conn->error . "<br>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        form { background: #f0f0f0; padding: 20px; border-radius: 5px; margin: 20px 0; }
        input { margin: 5px; padding: 8px; width: 200px; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 3px; }
    </style>
</head>
<body>
    <form method="POST">
        <h3>Test Login:</h3>
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
</body>
</html>
