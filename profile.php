<?php
session_start();
require_once 'config.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for remember-me token
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = filter_var($_COOKIE['remember_token'], FILTER_SANITIZE_STRING);
    $stmt = $conn->prepare("SELECT id, username, profile_picture, created_at FROM users WHERE remember_token = ? AND token_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['profile_picture'] = $user['profile_picture'];
        $_SESSION['created_at'] = $user['created_at'];
    }
    $stmt->close();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check for popup messages
$popup_message = '';
$popup_type = '';
if (isset($_SESSION['popup_message'])) {
    $popup_message = $_SESSION['popup_message'];
    $popup_type = $_SESSION['popup_type'];
    unset($_SESSION['popup_message']);
    unset($_SESSION['popup_type']);
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, profile_picture, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Format the created_at date
$created_date = new DateTime($user['created_at']);
$formatted_date = $created_date->format('F j, Y');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link rel="stylesheet" type="text/css" href="css/popup.css">
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
            background: rgba(255, 255, 255, 0.3); /* Light overlay */
            z-index: -1;
        }

        .container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6);
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

        .profile-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            margin: 0 auto 20px;
            border: 4px solid #4CAF50;
        }

        .profile-info {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-info p {
            margin: 10px 0;
            font-size: 16px;
            color: #555;
        }

        .profile-info strong {
            color: #333;
        }

        .member-since {
            color: #888;
            font-style: italic;
            font-size: 14px;
        }

        .actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            text-align: center;
        }

        .btn:hover {
            background-color: #45a049;
        }

        .btn-danger {
            background-color: #f44336;
        }

        .btn-danger:hover {
            background-color: #e53935;
        }

    </style>
</head>

<body>
<div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h2>

    <div class="profile-info">
        <img class="profile-img" src="uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
        <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        <p class="member-since">Member since: <?php echo $formatted_date; ?></p>
    </div>

    <div class="actions">
        <a href="edit_profile.php" class="btn">Edit Profile</a>
        <a href="logout.php" class="btn">Logout</a>
        <a href="delete_account.php" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">Delete Account</a>
    </div>
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

    <?php if (!empty($popup_message)): ?>
    showPopup("<?php echo addslashes($popup_message); ?>", "<?php echo $popup_type; ?>");
    <?php endif; ?>
</script>
</body>

</html>