<?php

use Random\RandomException;

session_start();
require_once 'config.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (RandomException $e) {
        $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true));
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['popup_message'] = "Please login to access your account";
    $_SESSION['popup_type'] = "error";
    header("Location: login.php");
    exit();
}

$error = "";

// Check if form was submitted and CSRF token is valid
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed";
    } else {
        $user_id = $_SESSION['user_id'];

        // Get user profile picture
        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Delete user from database
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            // Delete profile picture if it's not the default
            if ($user['profile_picture'] != "default.png" && file_exists('uploads/' . $user['profile_picture'])) {
                unlink('uploads/' . $user['profile_picture']);
            }

            // Destroy session
            $_SESSION = array();

            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }

            // Set popup message before destroying session
            $_SESSION['popup_message'] = "Your account has been successfully deleted";
            $_SESSION['popup_type'] = "info";

            // Clear any remember me cookie
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
            setcookie("remember_token", "", time() - 3600, "/", "", $secure, true);

            session_destroy();

            header("Location: register.php");
            exit();
        } else {
            $error = "Failed to delete your account. Please try again later.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Account</title>
    <link rel="stylesheet" href="css/popup.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            background: rgba(255, 255, 255, 0.3);
            z-index: -1;
        }

        .container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            padding: 30px;
            max-width: 600px;
            width: 100%;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .warning {
            background-color: #fff3f3;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            color: #333;
            font-size: 14px;
        }

        .warning strong {
            color: #f44336;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }

        .btn-danger {
            background-color: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background-color: #e53935;
        }

        .btn-secondary {
            background-color: #808080;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #6c6c6c;
        }

        .actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }


    </style>
</head>

<body>
<div class="container">
    <h2>Delete Account</h2>

    <div class="warning">
        <strong>Warning:</strong> This action cannot be undone. All your data will be permanently deleted.
    </div>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="actions">
            <button type="submit" class="btn btn-danger">Yes, Delete My Account</button>
            <a href="profile.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

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

    <?php if (!empty($error)): ?>
    showPopup("<?php echo addslashes($error); ?>", "error");
    <?php endif; ?>
</script>
</body>

</html>