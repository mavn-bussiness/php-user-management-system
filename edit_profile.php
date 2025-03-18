<?php
session_start();
require_once 'config.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Get current user data
$stmt = $conn->prepare("SELECT username, email, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed";
    } else {
        // Validate and sanitize input
        $username = trim(filter_var($_POST['username'], FILTER_SANITIZE_STRING));
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

        // Check if fields are empty
        if (empty($username) || empty($email)) {
            $error = "Username and email are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } else {
            // Check if email exists (for another user)
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Email already exists";
                $stmt->close();
            } else {
                $stmt->close();
                $profile_picture = $user['profile_picture'];

                // Handle file upload if a new image is selected
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
                            $max_size = 5 * 1024 * 1024;

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
                                    // Delete old profile picture if it's not the default
                                    if ($profile_picture != "default.png" && file_exists('uploads/' . $profile_picture)) {
                                        unlink('uploads/' . $profile_picture);
                                    }
                                    $profile_picture = $file_name;
                                } else {
                                    $error = "Error uploading file";
                                }
                            }
                        }
                    }
                }

                if (empty($error)) {
                    // Update user
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, profile_picture = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $username, $email, $profile_picture, $user_id);

                    if ($stmt->execute()) {
                        // Update session
                        $_SESSION['username'] = $username;
                        $_SESSION['profile_picture'] = $profile_picture;

                        // Set success message in session for display on profile page
                        $_SESSION['popup_message'] = "Profile updated successfully!";
                        $_SESSION['popup_type'] = "success";

                        // Redirect to profile page
                        header("Location: profile.php");
                        exit();
                    } else {
                        $error = "Error updating profile: " . $stmt->error;
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
    <title>Edit Profile</title>
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
            background: rgba(255, 255, 255, 0.6);
            z-index: -1;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0px 8px 20px rgba(0, 0, 0, 0.5);
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

        input[type="text"],
        input[type="email"],
        input[type="file"] {
            width: 100%;
            padding: 12px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="file"]:focus {
            border-color: #4CAF50;
            outline: none;
        }

        input[type="file"] {
            padding: 8px;
        }

        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
            border: 4px solid #ddd;
            transition: transform 0.3s ease, border-color 0.3s ease;
        }

        .profile-img:hover {
            transform: scale(1.05);
            border-color: #4CAF50;
        }

        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #f5c6cb;
        }

        .success {
            color: #28a745;
            background: #d4edda;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #c3e6cb;
        }

        .btn-container {
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }

        button, .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        button {
            background-color: #4CAF50;
            color: white;
        }

        button:hover {
            background-color: #45a049;
            transform: translateY(-2px);
        }

        .btn {
            background-color: #6c757d;
            color: white;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        .password-link {
            display: block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .password-link:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Edit Profile</h2>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <img class="profile-img" src="uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">

        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>

        <div class="form-group">
            <label for="profile_picture">Profile Picture (Max 5MB, JPG/JPEG/PNG only):</label>
            <input type="file" id="profile_picture" name="profile_picture">
            <small>Leave empty to keep the current profile picture</small>
        </div>

        <div class="btn-container">
            <button type="submit">Update Profile</button>
            <a href="profile.php" class="btn">Cancel</a>
        </div>
    </form>

    <a href="reset_password.php" class="password-link">Change Password</a>
</div>

</body>
</html>

