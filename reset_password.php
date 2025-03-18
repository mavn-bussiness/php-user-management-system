<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

session_start();
require_once 'config.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$success = "";
$showResetForm = false;
$token = "";

// Handle password reset link
if (isset($_GET['token'])) {
    $token = filter_var($_GET['token'], FILTER_SANITIZE_STRING);

    // Check if token is valid
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $showResetForm = true;
    } else {
        $error = "Invalid or expired reset token";
    }
    $stmt->close();
}

// Handle request for password reset
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed";
    } else {
        // Check if this is a reset password form submission
        if (isset($_POST['action']) && $_POST['action'] == 'reset_password') {
            $token = filter_var($_POST['token'], FILTER_SANITIZE_STRING);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            // Validate password
            if (empty($password) || empty($confirm_password)) {
                $error = "Both password fields are required";
                $showResetForm = true;
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match";
                $showResetForm = true;
            } elseif (strlen($password) < 8) {
                $error = "Password must be at least 8 characters long";
                $showResetForm = true;
            } elseif (!preg_match("#[0-9]+#", $password)) {
                $error = "Password must include at least one number";
                $showResetForm = true;
            } elseif (!preg_match("#[a-zA-Z]+#", $password)) {
                $error = "Password must include at least one letter";
                $showResetForm = true;
            } else {
                // Check if token is valid
                $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Update password and clear reset token
                    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $user['id']);

                    if ($stmt->execute()) {
                        $_SESSION['popup_message'] = "Your password has been updated successfully";
                        $_SESSION['popup_type'] = "success";
                        header("Location: login.php");
                        exit();
                    } else {
                        $error = "Error updating password";
                        $showResetForm = true;
                    }
                } else {
                    $error = "Invalid or expired reset token";
                }
                $stmt->close();
            }
        } else {
            // This is a request for password reset email
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Valid email is required";
            } else {
                // Check if email exists
                $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 0) {
                    $success = "If your email exists in our system, you will receive a password reset link shortly.";
                } else {
                    $user = $result->fetch_assoc();

                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + 3600);

                    // Store token in database
                    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $token, $expires, $user['id']);

                    if ($stmt->execute()) {
                        // Build reset link
                        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/USER_MANAGEMENT_SYSTEM" . "/reset_password.php?token=" . $token;

                        // Send email using PHPMailer
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'mpangamarvin2003@gmail.com';
                            $mail->Password = 'mlqq gwmd yamw arip';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;

                            $mail->setFrom('noreply@example.com', 'USER MANAGEMENT');
                            $mail->addAddress($email);

                            $mail->isHTML(true);
                            $mail->Subject = "Password Reset Request";
                            $mail->Body = "
<html>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
    <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; border-left: 4px solid #4285f4;'>
        <h2 style='color: #4285f4; margin-top: 0;'>Password Reset</h2>
        <p style='margin-bottom: 20px;'>Hello " . htmlspecialchars($user['username']) . ",</p>
        <p>You recently requested to reset your password. Click the button below to set a new password:</p>
        <div style='text-align: center; margin: 30px 0;'>
            <a href='$reset_link' style='background-color: #4285f4; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;'>Reset Password</a>
        </div>
        <p style='color: #666; font-size: 14px;'>This link will expire in 1 hour for security reasons.</p>
        <p style='color: #666; font-size: 14px;'>If you didn't request this password reset, you can safely ignore this email.</p>
    </div>
    <div style='text-align: center; margin-top: 20px; font-size: 12px; color: #666;'>
        <p>© " . date('Y') . " USER MANAGEMENT. All rights reserved.</p>
    </div>
</body>
</html>";

                            $mail->send();
                            $success = "If your email exists in our system, you will receive a password reset link shortly.";
                        } catch (Exception $e) {
                            error_log("Mailer Error: " . $mail->ErrorInfo);
                            $success = "If your email exists in our system, you will receive a password reset link shortly.";
                        }
                    }
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="css/popup.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image: url("images/background<?php echo rand(1,3)?>.jpg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.6); /* Light overlay */
            z-index: -1;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        h2 {
            margin-bottom: 25px;
            color: #333;
            font-size: 24px;
            font-weight: 600;
        }

        p {
            color: #555;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            font-weight: 500;
            display: block;
            margin-bottom: 8px;
            color: #555;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #4CAF50;
            outline: none;
        }

        small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        button:hover {
            background-color: #45a049;
            transform: translateY(-2px);
        }

        a {
            display: inline-block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        a:hover {
            color: #0056b3;
            text-decoration: underline;
        }


        @media (max-width: 480px) {
            .container {
                padding: 20px;
            }

            h2 {
                font-size: 20px;
            }

            p {
                font-size: 13px;
            }

            input[type="email"],
            input[type="password"] {
                padding: 10px;
                font-size: 13px;
            }

            button {
                padding: 10px;
                font-size: 13px;
            }
        }
    </style>
    <script>
        function showPopup(message, type) {
            const popup = document.createElement('div');
            popup.className = `popup ${type}`;
            popup.textContent = message;
            document.body.appendChild(popup);

            setTimeout(() => {
                popup.classList.add('show');
            }, 10);

            setTimeout(() => {
                popup.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(popup);
                }, 500);
            }, 3000);
        }
    </script>
</head>
<body>
<div class="container">
    <h2><?php echo $showResetForm ? 'Create New Password' : 'Reset Password'; ?></h2>

    <?php if ($showResetForm): ?>
        <!-- Reset Password Form -->
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="action" value="reset_password">

            <div class="form-group">
                <label for="password">New Password:</label>
                <input type="password" id="password" name="password" required>
                <small>Password must be at least 8 characters long and include at least one letter and one number.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit">Update Password</button>
        </form>
    <?php else: ?>
        <!-- Request Reset Form -->
        <p>Enter your email address, and we'll send you a link to reset your password.</p>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <button type="submit">Send Reset Link</button>
        </form>
    <?php endif; ?>

    <a href="login.php">Back to Login</a>
</div>

<script>
    <?php if (!empty($error)): ?>
    showPopup("<?php echo addslashes($error); ?>", "error");
    <?php endif; ?>

    <?php if (!empty($success)): ?>
    showPopup("<?php echo addslashes($success); ?>", "success");
    <?php endif; ?>
</script>
</body>
</html>
