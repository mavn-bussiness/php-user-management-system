<?php
session_start();
require_once 'config.php';

$error = "";

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    // Validate input
    if (empty($email) || empty($password)) {
        $error = "Email and password are required";
    } else {
        // Check user credentials
        $stmt = $conn->prepare("SELECT id, username, password, profile_picture, created_at FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['profile_picture'] = $user['profile_picture'];
                $_SESSION['created_at'] = $user['created_at'];

                // Handle "Remember Me" functionality
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days

                    $stmt = $conn->prepare("UPDATE users SET remember_token = ?, token_expires = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $token, $expiry, $user['id']);
                    $stmt->execute();

                    setcookie("remember_token", $token, time() + (30 * 24 * 60 * 60), "/", "", true, true);
                }

                // Set success message
                $_SESSION['popup_message'] = "Login successful!";
                $_SESSION['popup_type'] = "success";

                header("Location: profile.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
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
    <title>User Login</title>
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
            background: rgba(255, 255, 255, 0.6); /* Light overlay */
            z-index: -1;
        }

        .container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            padding: 30px;
            max-width: 500px;
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

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 14px;
            color: #555;
            margin-bottom: 5px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #4CAF50;
            outline: none;
        }

        .checkbox-label {
            display: inline-block;
            margin-left: 5px;
            font-size: 14px;
            color: #555;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #45a049;
        }

        a {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #4CAF50;
            text-decoration: none;
            font-size: 14px;
        }

        a:hover {
            text-decoration: underline;
        }

        .error {
            color: #f44336;
            font-size: 14px;
            margin-bottom: 15px;
            text-align: center;
        }

        .forgot-password {
            display: block;
            margin-top: 10px;
            margin-bottom: 15px;
            font-size: 0.9em;
            color: #4CAF50;
            text-align: right;
        }

    </style>
</head>

<body>
<div class="container">
    <h2>Login</h2>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember" class="checkbox-label">Remember me</label>
        </div>

        <button type="submit">Login</button>

        <a href="reset_password.php" class="forgot-password">Forgot password?</a>
    </form>

    <a href="register.php">New user? Register here</a>
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