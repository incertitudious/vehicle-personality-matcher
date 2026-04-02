<?php
session_start();
require_once "includes/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {

        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: user/dashboard.php");
            }
            exit;
        }
    }

    $error = "Invalid email or password.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

<style>

body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #4c1d95, #1e1b4b);
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* CARD */
.auth-card {
    display: flex;
    width: 900px;
    border-radius: 16px;
    overflow: hidden;
    backdrop-filter: blur(20px);
    background: rgba(255,255,255,0.05);
    box-shadow: 0 20px 50px rgba(0,0,0,0.4);
}

/* LEFT PANEL */
.auth-left {
    flex: 1;
    padding: 40px;
    background: linear-gradient(135deg, #6366f1, #9333ea);
    color: white;
}

.auth-left h2 {
    margin-top: 10px;
}

.auth-left img {
    width: 100%;
    margin-top: 20px;
    border-radius: 12px;
}

/* RIGHT PANEL */
.auth-right {
    flex: 1;
    padding: 40px;
    color: white;
}

.auth-right h2 {
    margin-bottom: 5px;
}

.subtitle {
    font-size: 14px;
    color: #cbd5f5;
    margin-bottom: 25px;
}

/* INPUTS */
.input-group {
    margin-bottom: 15px;
}

.input-group input {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: none;
    background: rgba(255,255,255,0.08);
    color: white;
}

/* ROW */
.form-row {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 15px;
}

.form-row a {
    font-size: 13px;
    color: #cbd5f5;
    text-decoration: none;
}

/* BUTTON */
.btn {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(90deg, #9333ea, #3b82f6);
    color: white;
    font-weight: bold;
    cursor: pointer;
}

/* TEXT */
.bottom-text {
    margin-top: 20px;
    font-size: 14px;
    text-align: center;
}

.bottom-text a {
    color: #60a5fa;
    text-decoration: none;
}

.error {
    color: #f87171;
    margin-bottom: 10px;
}

</style>
</head>

<body>

<div class="auth-card">

    <!-- LEFT -->
    <div class="auth-left">
        <h3>🚗 Vehicle Personality</h3>
        <h2>Find Your Perfect Match</h2>

        <p>
            Discover the vehicle that matches your unique personality and lifestyle.
        </p>

         <img src="assets\images\car_bike.png" alt="cars">
    </div>

    <!-- RIGHT -->
    <div class="auth-right">
        <h2>Welcome Back</h2>
        <p class="subtitle">Sign in to continue your journey</p>

        <?php if ($error): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>

        <form method="POST">

            <div class="input-group">
                <input type="email" name="email" required placeholder="Email Address">
            </div>

            <div class="input-group">
                <input type="password" name="password" required placeholder="Password">
            </div>

            <div class="form-row">
                <a href="forgot-password.php">Forgot Password?</a>
            </div>

            <button type="submit" class="btn">
                Sign In
            </button>

        </form>

        <p class="bottom-text">
            Don't have an account?
            <a href="register.php">Sign up</a>
        </p>
    </div>

</div>

</body>
</html>