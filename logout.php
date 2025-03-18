<?php
session_start();

if (isset($_COOKIE['remember_token'])) {
    setcookie("remember_token", "", time() - 3600, "/", "", true, true);
}

$_SESSION['popup_message'] = "You have been logged out successfully";
$_SESSION['popup_type'] = "info";

session_unset();
session_destroy();

header("Location: login.php");
exit();
