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

// Database connection
$db_host = '127.0.0.1';
$db_port = 3306;
$db_user = 'inf2003-sqldev';
$db_pass = 'Inf2003#DevSecure!2025';
$db_name = 'yourtrip_db';

$step = 'email'; // Steps: email, verify, reset, success
$error = null;
$success = null;
$email = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_reset') {
        // Step 1: Request password reset
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            try {
                $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                }

                // Check if email exists
                $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    // Generate a simple 6-digit verification code
                    $resetCode = sprintf("%06d", mt_rand(1, 999999));
                    $resetExpiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                    // Store reset code in session (in production, you'd send this via email)
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_code'] = $resetCode;
                    $_SESSION['reset_expiry'] = $resetExpiry;
                    $_SESSION['reset_user_id'] = $user['id'];

                    $step = 'verify';
                    $success = "A verification code has been generated. (In production, this would be sent to your email)";

                    // For demonstration purposes, show the code
                    $success .= " <br><strong>Your verification code is: " . $resetCode . "</strong>";

                } else {
                    // For security, don't reveal if email doesn't exist
                    $step = 'verify';
                    $success = "If an account with this email exists, a verification code has been sent.";
                    $_SESSION['reset_email'] = $email;
                }

                $stmt->close();
                $conn->close();

            } catch (Exception $e) {
                error_log("Database error: " . $e->getMessage());
                $error = "A system error occurred. Please try again later.";
            }
        }
    } elseif ($action === 'verify_code') {
        // Step 2: Verify the reset code
        $email = $_SESSION['reset_email'] ?? '';
        $code = trim($_POST['code'] ?? '');

        if (empty($code)) {
            $error = 'Verification code is required';
            $step = 'verify';
        } elseif (!isset($_SESSION['reset_code']) || !isset($_SESSION['reset_expiry'])) {
            $error = 'Session expired. Please start over.';
            $step = 'email';
        } elseif (strtotime($_SESSION['reset_expiry']) < time()) {
            $error = 'Verification code has expired. Please request a new one.';
            $step = 'email';
            unset($_SESSION['reset_code'], $_SESSION['reset_expiry'], $_SESSION['reset_email']);
        } elseif ($code !== $_SESSION['reset_code']) {
            $error = 'Invalid verification code';
            $step = 'verify';
        } else {
            $step = 'reset';
            $success = 'Code verified! Please enter your new password.';
        }
    } elseif ($action === 'reset_password') {
        // Step 3: Reset the password
        $email = $_SESSION['reset_email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';

        if (!isset($_SESSION['reset_user_id'])) {
            $error = 'Session expired. Please start over.';
            $step = 'email';
        } elseif (empty($password)) {
            $error = 'Password is required';
            $step = 'reset';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
            $step = 'reset';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
            $error = 'Password must contain uppercase, lowercase, number, and special character';
            $step = 'reset';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
            $step = 'reset';
        } else {
            try {
                $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                }

                // Hash the new password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Update the password
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashedPassword, $_SESSION['reset_user_id']);

                if ($stmt->execute()) {
                    $step = 'success';
                    // Clear reset session data
                    unset($_SESSION['reset_code'], $_SESSION['reset_expiry'], $_SESSION['reset_email'], $_SESSION['reset_user_id']);
                } else {
                    $error = "Failed to reset password. Please try again.";
                    $step = 'reset';
                }

                $stmt->close();
                $conn->close();

            } catch (Exception $e) {
                error_log("Database error: " . $e->getMessage());
                $error = "A system error occurred. Please try again later.";
                $step = 'reset';
            }
        }
    }
} else {
    // Preserve email from session if available
    $email = $_SESSION['reset_email'] ?? '';
    if (!empty($email) && isset($_SESSION['reset_code'])) {
        $step = 'verify';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YourTrip - Reset Password</title>
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

        .reset-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.2),
                        0 0 0 1px rgba(255, 255, 255, 0.3);
            overflow: visible;
            width: 100%;
            max-width: 480px;
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

        .reset-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-radius: 30px 30px 0 0;
        }

        .reset-header::before {
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

        .reset-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
            position: relative;
            z-index: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .reset-header p {
            font-size: 14px;
            opacity: 0.95;
            font-weight: 300;
            position: relative;
            z-index: 1;
            letter-spacing: 0.5px;
        }

        .reset-form {
            padding: 35px 35px 35px 35px;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .step.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .step.completed {
            background: #10b981;
            color: white;
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

        .success-message {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
            animation: fadeInError 0.3s ease;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
            line-height: 1.6;
        }

        .error-message {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
            animation: fadeInError 0.3s ease;
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }

        .error-message::before {
            content: '⚠';
            margin-right: 10px;
            font-size: 20px;
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

        .password-requirements {
            font-size: 12px;
            color: #64748b;
            margin-top: 8px;
            line-height: 1.6;
        }

        .password-requirements ul {
            margin: 8px 0 0 20px;
            padding: 0;
        }

        .password-requirements li {
            margin: 4px 0;
        }

        .reset-btn {
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

        .reset-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .reset-btn:hover::before {
            left: 100%;
        }

        .reset-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        .reset-btn:active {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            position: relative;
        }

        .login-link a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .login-link a:hover::after {
            width: 100%;
        }

        .success-icon {
            font-size: 64px;
            text-align: center;
            margin-bottom: 20px;
            animation: scaleIn 0.5s ease;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .info-text {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
            text-align: center;
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .reset-container {
                max-width: 100%;
            }

            .reset-header h1 {
                font-size: 28px;
            }

            .reset-form {
                padding: 0 25px 25px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <h1>Reset Password</h1>
            <p>Recover your YourTrip account</p>
        </div>

        <form class="reset-form" method="POST" action="forget_password.php">
            <?php if ($step !== 'success'): ?>
                <div class="step-indicator">
                    <div class="step <?php echo $step === 'email' ? 'active' : ($step !== 'email' ? 'completed' : ''); ?>">1</div>
                    <div class="step <?php echo $step === 'verify' ? 'active' : ($step === 'reset' || $step === 'success' ? 'completed' : ''); ?>">2</div>
                    <div class="step <?php echo $step === 'reset' ? 'active' : ($step === 'success' ? 'completed' : ''); ?>">3</div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 'email'): ?>
                <!-- Step 1: Enter Email -->
                <div class="info-text">
                    Enter your email address and we'll send you a verification code to reset your password.
                </div>

                <input type="hidden" name="action" value="request_reset">

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email"
                            value="<?php echo htmlspecialchars($email); ?>"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <button type="submit" class="reset-btn">Send Verification Code</button>

            <?php elseif ($step === 'verify'): ?>
                <!-- Step 2: Verify Code -->
                <div class="info-text">
                    A 6-digit verification code has been sent to <strong><?php echo htmlspecialchars($email); ?></strong>
                </div>

                <input type="hidden" name="action" value="verify_code">

                <div class="form-group">
                    <label for="code">Verification Code</label>
                    <div class="input-wrapper">
                        <input
                            type="text"
                            id="code"
                            name="code"
                            placeholder="Enter 6-digit code"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <button type="submit" class="reset-btn">Verify Code</button>

            <?php elseif ($step === 'reset'): ?>
                <!-- Step 3: Reset Password -->
                <div class="info-text">
                    Create a new password for your account.
                </div>

                <input type="hidden" name="action" value="reset_password">

                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a new password"
                            required
                            autofocus
                        >
                        <span class="password-toggle" onclick="togglePassword('password')">🐵</span>
                    </div>
                    <div class="password-requirements">
                        Password must contain:
                        <ul>
                            <li>At least 8 characters</li>
                            <li>Uppercase and lowercase letters</li>
                            <li>At least one number</li>
                            <li>At least one special character (@$!%*?&)</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm New Password</label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="confirmPassword"
                            name="confirmPassword"
                            placeholder="Re-enter your new password"
                            required
                        >
                        <span class="password-toggle" onclick="togglePassword('confirmPassword')">🐵</span>
                    </div>
                </div>

                <button type="submit" class="reset-btn">Reset Password</button>

            <?php elseif ($step === 'success'): ?>
                <!-- Step 4: Success -->
                <div class="success-icon">✓</div>
                <div class="info-text" style="font-size: 16px; font-weight: 600; color: #334155;">
                    Password Reset Successful!
                </div>
                <div class="info-text">
                    Your password has been successfully reset. You can now log in with your new password.
                </div>

                <a href="login.php" style="text-decoration: none;">
                    <button type="button" class="reset-btn">Go to Login</button>
                </a>

                <script>
                    // Auto-redirect after 5 seconds
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 5000);
                </script>
            <?php endif; ?>

            <div class="login-link">
                Remember your password? <a href="login.php">Back to Login</a>
            </div>
        </form>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleBtn = passwordInput.parentElement.querySelector('.password-toggle');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '🐵';
            }
        }
    </script>
</body>
</html>
