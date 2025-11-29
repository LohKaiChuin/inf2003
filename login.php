<?php
session_start();

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin-dashboard.php');
    } else {
        header('Location: user-dashboard.php');
    }
    exit();
}

// Check for registration success
$registrationSuccess = false;
$registeredUsername = '';
if (isset($_SESSION['registration_success']) && $_SESSION['registration_success'] === true) {
    $registrationSuccess = true;
    $registeredUsername = $_SESSION['registered_username'] ?? '';
    // Clear the session variables
    unset($_SESSION['registration_success']);
    unset($_SESSION['registered_username']);
}

require_once 'config.php';

// Handle login POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['rememberMe']);

    $user = null;
    $role = null;

    try {
        // Create database connection
        $pdo = getDBConnection();
        $conn = $pdo; // for compatibility with existing code if needed, but better to switch to PDO methods

        // Prepare statement to prevent SQL injection
        // Assuming you have a 'users' table with columns: id, username, email, password, role
        $stmt = $conn->prepare("SELECT id, username, email, password, role FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $userData = $result->fetch_assoc();

            // Verify password (assuming passwords are hashed in database)
            // If passwords are stored in plain text (NOT RECOMMENDED), use: $userData['password'] === $password
            if (password_verify($password, $userData['password'])) {
                $user = [
                    'id' => $userData['id'],
                    'username' => $userData['username'],
                    'email' => $userData['email']
                ];
                $role = $userData['role'];
            }
        }

        $stmt->close();

    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $error = "A system error occurred. Please try again later.";
    }

    if ($user) {
        // Successful login - Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $role;
        $_SESSION['login_time'] = date('Y-m-d H:i:s');

        // Also keep the user array for backward compatibility
        $_SESSION['user'] = [
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $role,
            'login_time' => date('Y-m-d H:i:s')
        ];

        // Set remember me cookie if checked
        if ($rememberMe) {
            setcookie('ltaWannabeUser', json_encode([
                'username' => $user['username'],
                'role' => $role
            ]), time() + (86400 * 30), '/'); // 30 days
        }

        // Redirect based on role
        if ($role === 'admin') {
            header('Location: admin-dashboard.php');
        } else {
            header('Location: user-dashboard.php');
        }
        exit();
    } else {
        // Failed login
        $error = 'Invalid username/email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YourTrip - Login</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            height: 100%;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Floating background elements */
        body::before,
        body::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(100px);
            animation: float 20s infinite ease-in-out;
        }

        body::before {
            top: -200px;
            left: -200px;
            animation-delay: 0s;
        }

        body::after {
            bottom: -200px;
            right: -200px;
            animation-delay: 5s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-50px) rotate(180deg); }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.2),
                        0 0 0 1px rgba(255, 255, 255, 0.3);
            overflow: visible;
            width: 100%;
            max-width: 450px;
            animation: slideIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            z-index: 10;
            margin: auto;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-radius: 30px 30px 0 0;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }

        .login-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
            position: relative;
            z-index: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .login-header p {
            font-size: 14px;
            opacity: 0.95;
            font-weight: 300;
            position: relative;
            z-index: 1;
            letter-spacing: 0.5px;
        }

        .login-form {
            padding: 35px 35px 35px 35px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #334155;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
            background: #f8fafc;
        }

        .input-wrapper input:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1),
                        0 4px 12px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }

        .input-wrapper input.error {
            border-color: #ef4444;
            background: #fef2f2;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .error-message {
            color: #ef4444;
            font-size: 12px;
            margin-top: 8px;
            display: none;
            font-weight: 500;
            animation: fadeInError 0.3s ease;
        }

        .error-message.show {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .error-message::before {
            content: '⚠';
            font-size: 14px;
        }

        .success-message {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 14px;
            text-align: center;
            animation: fadeInError 0.3s ease;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .success-message::before {
            content: '✓';
            font-size: 18px;
        }

        @keyframes fadeInError {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 20px;
            transition: all 0.3s ease;
            user-select: none;
        }

        .password-toggle:hover {
            color: #667eea;
            transform: translateY(-50%) scale(1.1);
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            color: #475569;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .remember-me:hover {
            color: #667eea;
        }

        .remember-me input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #667eea;
            border-radius: 4px;
        }

        .forgot-password {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            position: relative;
            transition: all 0.3s ease;
        }

        .forgot-password::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .forgot-password:hover::after {
            width: 100%;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            position: relative;
            overflow: hidden;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        .login-btn:active {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .create-account {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        .create-account a {
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            position: relative;
        }

        .create-account a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .create-account a:hover::after {
            width: 100%;
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
            }

            .login-header h1 {
                font-size: 28px;
            }

            .login-form {
                padding: 0 25px 25px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>YourTrip</h1>
            <p>Transport Management Platform</p>
        </div>

        <form class="login-form" method="POST" action="login.php">
            <?php if ($registrationSuccess): ?>
                <div class="success-message">
                    Account created successfully! Please login with your credentials.
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="error-message show" style="margin-bottom: 20px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Username or Email</label>
                <div class="input-wrapper">
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter your username or email"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ($registrationSuccess && !empty($registeredUsername) ? htmlspecialchars($registeredUsername) : ''); ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >
                    <span class="password-toggle" onclick="togglePassword()">🐵</span>
                </div>
            </div>

            <div class="remember-forgot">
                <label class="remember-me">
                    <input type="checkbox" id="rememberMe" name="rememberMe">
                    <span>Remember me</span>
                </label>
                <a href="forget_password.php" class="forgot-password">Forgot Password?</a>
            </div>

            <button type="submit" class="login-btn">Login</button>

            <div class="create-account">
                Don't have an account? <a href="register.php">Create Account</a>
            </div>
        </form>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '🐵';
            }
        }

        // Check for remembered user on page load (from cookie)
        window.addEventListener('load', function() {
            const cookies = document.cookie.split(';');
            for (let cookie of cookies) {
                const [name, value] = cookie.trim().split('=');
                if (name === 'ltaWannabeUser') {
                    try {
                        const userData = JSON.parse(decodeURIComponent(value));
                        document.getElementById('username').value = userData.username || '';
                        document.getElementById('rememberMe').checked = true;
                    } catch (e) {
                        console.error('Error parsing cookie:', e);
                    }
                }
            }
        });
    </script>
</body>
</html>
