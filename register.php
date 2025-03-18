<?php
session_start();
require_once 'config.php';

$error = "";
$success = "";

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed";
    } else {
        $username = trim(htmlspecialchars($_POST['username']));
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];

        if (empty($username) || empty($email) || empty($password)) {
            $error = "All fields are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long";
        } elseif (!preg_match("#[0-9]+#", $password)) {
            $error = "Password must include at least one number";
        } elseif (!preg_match("#[a-zA-Z]+#", $password)) {
            $error = "Password must include at least one letter";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Email already exists";
                $stmt->close();
            } else {
                $stmt->close();

                $profile_picture = "default.png";

                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                    // Verify MIME type using getimagesize()
                    $image_info = getimagesize($_FILES['profile_picture']['tmp_name']);
                    if ($image_info === false) {
                        $error = "Uploaded file is not a valid image";
                    } else {
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                        if (!in_array($image_info['mime'], $allowed_types)) {
                            $error = "Only JPG, JPEG, and PNG files are allowed";
                        } else {
                            $max_size = 5 * 1024 * 1024; // 5MB

                            if ($_FILES['profile_picture']['size'] > $max_size) {
                                $error = "File size must be less than 5MB";
                            } else {
                                if (!file_exists('uploads')) {
                                    mkdir('uploads', 0777, true);
                                }

                                // Generate a unique filename
                                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                                $file_name = bin2hex(random_bytes(16)) . 'USER_MANAGEMENT_SYSTEM' . $file_extension;
                                $target_file = 'uploads/' . $file_name;

                                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                                    $profile_picture = $file_name;
                                } else {
                                    $error = "Error uploading file";
                                }
                            }
                        }
                    }
                }

                if (empty($error)) {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $created_at = date('Y-m-d H:i:s');

                    // Insert user
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password, profile_picture, created_at) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $username, $email, $hashed_password, $profile_picture, $created_at);

                    if ($stmt->execute()) {
                        $_SESSION['popup_message'] = "Registration successful! You can now login.";
                        $_SESSION['popup_type'] = "success";
                        header('location: login.php');
                        exit();
                    } else {
                        $error = "Error: " . $stmt->error;
                    }
                    $stmt->close();
                }
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
    <title>User Registration</title>
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
            background: rgba(255, 255, 255, 0.6);
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

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="file"]:focus {
            border-color: #4CAF50;
            outline: none;
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

        .success {
            color: #4CAF50;
            font-size: 14px;
            margin-bottom: 15px;
            text-align: center;
        }


    </style>
</head>

<body>
<div class="container">
    <h2>Register New User</h2>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            <small style="color: #888; font-size: 12px;">Password must be at least 8 characters long and include at least one letter and one number.</small>
        </div>

        <div class="form-group">
            <label for="profile_picture">Profile Picture (Max 5MB, JPG/JPEG/PNG only):</label>
            <input type="file" id="profile_picture" name="profile_picture">
        </div>

        <button type="submit">Register</button>
    </form>

    <a href="login.php">Already registered? Login here</a>
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