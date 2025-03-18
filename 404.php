<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found</title>
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
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .error-code {
            font-size: 72px;
            font-weight: 700;
            color: #4CAF50;
            margin: 0;
            line-height: 1;
        }
        h1 {
            margin: 10px 0 25px;
            color: #333;
            font-size: 28px;
        }
        p {
            color: #555;
            margin-bottom: 25px;
            font-size: 16px;
            line-height: 1.5;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .button:hover {
            background-color: #45a049;
            transform: translateY(-2px);
        }
        .links {
            margin-top: 25px;
        }
        .links a {
            display: inline-block;
            margin: 0 10px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        .links a:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        @media (max-width: 480px) {
            .container {
                padding: 25px;
            }
            .error-code {
                font-size: 50px;
            }
            h1 {
                font-size: 22px;
            }
            p {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <p class="error-code">404</p>
    <h1>Page Not Found</h1>
    <p>The page you're looking for doesn't exist or may have been moved.</p>
    <a href="index.php" class="button">Return to Home</a>
    <div class="links">
        <a href="login.php">Login</a>
        <a href="contact.php">Contact Support</a>
    </div>
</div>
</body>
</html>
