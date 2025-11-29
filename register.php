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

$success = false;
$error = null;
$fieldErrors = [];

// Handle registration POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Validation
    if (empty($username)) {
        $fieldErrors['username'] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $fieldErrors['username'] = 'Username must be at least 3 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $fieldErrors['username'] = 'Username can only contain letters, numbers, and underscores';
    }

    if (empty($email)) {
        $fieldErrors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Invalid email format';
    }

    if (empty($password)) {
        $fieldErrors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $fieldErrors['password'] = 'Password must be at least 8 characters';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
        $fieldErrors['password'] = 'Password must contain uppercase, lowercase, number, and special character';
    }

    if (empty($confirmPassword)) {
        $fieldErrors['confirmPassword'] = 'Please confirm your password';
    } elseif ($password !== $confirmPassword) {
        $fieldErrors['confirmPassword'] = 'Passwords do not match';
    }

    // If no validation errors, proceed with registration
    if (empty($fieldErrors)) {
        try {
            // Create database connection
            $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

            // Check connection
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }

            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $fieldErrors['username'] = 'Username already exists';
            }
            $stmt->close();

            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $fieldErrors['email'] = 'Email already registered';
            }
            $stmt->close();

            // If no duplicate errors, create the account
            if (empty($fieldErrors)) {
                // Hash the password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user with default role 'user'
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
                $stmt->bind_param("sss", $username, $email, $hashedPassword);

                if ($stmt->execute()) {
                    $success = true;
                    // Store success message in session and redirect
                    $_SESSION['registration_success'] = true;
                    $_SESSION['registered_username'] = $username;

                    // Redirect to login page
                    header('Location: login.php?registered=1');
                    exit();
                } else {
                    $error = "Registration failed. Please try again.";
                }

                $stmt->close();
            }

            $conn->close();

        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            $error = "A system error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YourTrip - Create Account</title>
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

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.2),
                        0 0 0 1px rgba(255, 255, 255, 0.3);
            overflow: visible;
            width: 100%;
            max-width: 500px;
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

        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-radius: 30px 30px 0 0;
        }

        .register-header::before {
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

        .register-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 700;
            position: relative;
            z-index: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .register-header p {
            font-size: 14px;
            opacity: 0.95;
            font-weight: 300;
            position: relative;
            z-index: 1;
            letter-spacing: 0.5px;
        }

        .register-form {
            padding: 25px 35px 30px 35px;
        }

        .form-group {
            margin-bottom: 18px;
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
            padding: 12px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
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
        }

        .field-error {
            color: #ef4444;
            font-size: 12px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            animation: fadeInError 0.3s ease;
        }

        .field-error::before {
            content: '⚠';
            font-size: 14px;
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

        .success-message {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 15px;
            font-weight: 500;
            font-size: 14px;
            text-align: center;
            animation: fadeInError 0.3s ease;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .success-message::before {
            content: '✓';
            margin-right: 10px;
            font-size: 20px;
        }

        .error-message {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 15px;
            font-weight: 500;
            font-size: 14px;
            text-align: center;
            animation: fadeInError 0.3s ease;
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }

        .error-message::before {
            content: '⚠';
            margin-right: 10px;
            font-size: 20px;
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
            font-size: 11px;
            color: #64748b;
            margin-top: 6px;
            line-height: 1.5;
        }

        .password-requirements ul {
            margin: 4px 0 0 20px;
            padding: 0;
        }

        .password-requirements li {
            margin: 2px 0;
        }

        .register-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            position: relative;
            overflow: hidden;
        }

        .register-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .register-btn:hover::before {
            left: 100%;
        }

        .register-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
        }

        .register-btn:active {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .login-link {
            text-align: center;
            margin-top: 18px;
            font-size: 13px;
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

        .back-to-login {
            text-align: center;
            margin-bottom: 20px;
        }

        .back-to-login a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .back-to-login a:hover {
            gap: 12px;
            color: #764ba2;
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .register-container {
                max-width: 100%;
            }

            .register-header h1 {
                font-size: 28px;
            }

            .register-form {
                padding: 0 25px 25px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Create Account</h1>
            <p>Join YourTrip Platform</p>
        </div>

        <form class="register-form" method="POST" action="register.php">
            <?php if ($success): ?>
                <div class="success-message">
                    Account created successfully! Redirecting to login...
                </div>
                <script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 2000);
                </script>
            <?php endif; ?>

            <?php if ($error && !$success): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Choose a username"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        class="<?php echo isset($fieldErrors['username']) ? 'error' : ''; ?>"
                        required
                        <?php echo $success ? 'disabled' : ''; ?>
                    >
                </div>
                <?php if (isset($fieldErrors['username'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($fieldErrors['username']); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        class="<?php echo isset($fieldErrors['email']) ? 'error' : ''; ?>"
                        required
                        <?php echo $success ? 'disabled' : ''; ?>
                    >
                </div>
                <?php if (isset($fieldErrors['email'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($fieldErrors['email']); ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Create a password"
                        class="<?php echo isset($fieldErrors['password']) ? 'error' : ''; ?>"
                        required
                        <?php echo $success ? 'disabled' : ''; ?>
                    >
                    <span class="password-toggle" onclick="togglePassword('password')">🐵</span>
                </div>
                <?php if (isset($fieldErrors['password'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($fieldErrors['password']); ?></div>
                <?php else: ?>
                    <div class="password-requirements">
                        Password must contain:
                        <ul>
                            <li>At least 8 characters</li>
                            <li>Uppercase and lowercase letters</li>
                            <li>At least one number</li>
                            <li>At least one special character (@$!%*?&)</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirmPassword">Confirm Password</label>
                <div class="input-wrapper">
                    <input
                        type="password"
                        id="confirmPassword"
                        name="confirmPassword"
                        placeholder="Re-enter your password"
                        class="<?php echo isset($fieldErrors['confirmPassword']) ? 'error' : ''; ?>"
                        required
                        <?php echo $success ? 'disabled' : ''; ?>
                    >
                    <span class="password-toggle" onclick="togglePassword('confirmPassword')">🐵</span>
                </div>
                <?php if (isset($fieldErrors['confirmPassword'])): ?>
                    <div class="field-error"><?php echo htmlspecialchars($fieldErrors['confirmPassword']); ?></div>
                <?php endif; ?>
            </div>

            <?php if (!$success): ?>
                <button type="submit" class="register-btn">Create Account</button>
            <?php endif; ?>

            <div class="login-link">
                Already have an account? <a href="login.php">Login</a>
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

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.register-form');
            const username = document.getElementById('username');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirmPassword');

            // Real-time validation for username
            username.addEventListener('blur', function() {
                validateUsername();
            });

            username.addEventListener('input', function() {
                // Remove error styling while typing
                username.classList.remove('error');
                const existingError = username.parentElement.parentElement.querySelector('.field-error');
                if (existingError && !existingError.textContent.includes('already exists')) {
                    existingError.remove();
                }
            });

            // Real-time validation for email
            email.addEventListener('blur', function() {
                validateEmail();
            });

            email.addEventListener('input', function() {
                email.classList.remove('error');
                const existingError = email.parentElement.parentElement.querySelector('.field-error');
                if (existingError && !existingError.textContent.includes('already registered')) {
                    existingError.remove();
                }
            });

            // Real-time validation for password
            password.addEventListener('input', function() {
                password.classList.remove('error');
                const existingError = password.parentElement.parentElement.querySelector('.field-error');
                if (existingError) {
                    existingError.remove();
                }
            });

            password.addEventListener('blur', function() {
                validatePassword();
            });

            // Real-time validation for confirm password
            confirmPassword.addEventListener('input', function() {
                confirmPassword.classList.remove('error');
                const existingError = confirmPassword.parentElement.parentElement.querySelector('.field-error');
                if (existingError) {
                    existingError.remove();
                }
            });

            confirmPassword.addEventListener('blur', function() {
                validateConfirmPassword();
            });

            // Form submission validation
            form.addEventListener('submit', function(e) {
                // Clear previous errors
                document.querySelectorAll('.field-error').forEach(function(error) {
                    if (!error.classList.contains('show')) {
                        error.remove();
                    }
                });

                let isValid = true;

                // Validate all fields
                if (!validateUsername()) isValid = false;
                if (!validateEmail()) isValid = false;
                if (!validatePassword()) isValid = false;
                if (!validateConfirmPassword()) isValid = false;

                if (!isValid) {
                    e.preventDefault();
                    // Scroll to first error
                    const firstError = document.querySelector('.error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                }
            });

            function validateUsername() {
                const value = username.value.trim();
                const formGroup = username.parentElement.parentElement;

                // Remove existing error
                const existingError = formGroup.querySelector('.field-error');
                if (existingError && !existingError.textContent.includes('already exists')) {
                    existingError.remove();
                }

                if (value === '') {
                    showError(username, 'Username is required');
                    return false;
                } else if (value.length < 3) {
                    showError(username, 'Username must be at least 3 characters');
                    return false;
                } else if (!/^[a-zA-Z0-9_]+$/.test(value)) {
                    showError(username, 'Username can only contain letters, numbers, and underscores');
                    return false;
                }

                username.classList.remove('error');
                return true;
            }

            function validateEmail() {
                const value = email.value.trim();
                const formGroup = email.parentElement.parentElement;

                // Remove existing error
                const existingError = formGroup.querySelector('.field-error');
                if (existingError && !existingError.textContent.includes('already registered')) {
                    existingError.remove();
                }

                if (value === '') {
                    showError(email, 'Email is required');
                    return false;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    showError(email, 'Invalid email format');
                    return false;
                }

                email.classList.remove('error');
                return true;
            }

            function validatePassword() {
                const value = password.value;
                const formGroup = password.parentElement.parentElement;

                // Remove existing error
                const existingError = formGroup.querySelector('.field-error');
                if (existingError) {
                    existingError.remove();
                }

                if (value === '') {
                    showError(password, 'Password is required');
                    return false;
                } else if (value.length < 8) {
                    showError(password, 'Password must be at least 8 characters');
                    return false;
                } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])/.test(value)) {
                    showError(password, 'Password must contain uppercase, lowercase, number, and special character');
                    return false;
                }

                password.classList.remove('error');
                return true;
            }

            function validateConfirmPassword() {
                const value = confirmPassword.value;
                const formGroup = confirmPassword.parentElement.parentElement;

                // Remove existing error
                const existingError = formGroup.querySelector('.field-error');
                if (existingError) {
                    existingError.remove();
                }

                if (value === '') {
                    showError(confirmPassword, 'Please confirm your password');
                    return false;
                } else if (value !== password.value) {
                    showError(confirmPassword, 'Passwords do not match');
                    return false;
                }

                confirmPassword.classList.remove('error');
                return true;
            }

            function showError(input, message) {
                input.classList.add('error');
                const formGroup = input.parentElement.parentElement;

                // Check if error already exists
                let errorDiv = formGroup.querySelector('.field-error');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'field-error';
                    formGroup.appendChild(errorDiv);
                }

                errorDiv.textContent = message;
            }
        });
    </script>
</body>
</html>
