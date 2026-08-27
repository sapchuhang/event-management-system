<?php
require_once 'config/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        $error = "Invalid CSRF token! Please try again.";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];
            header('Location: admin/dashboard.php');
            exit;
        } else {
            $error = "Invalid username or password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – SUYOGYA SACCOS Event Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #eef4f4;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Background corner diamonds */
        body::before,
        body::after {
            content: '';
            position: fixed;
            width: 220px;
            height: 220px;
            background: linear-gradient(135deg, #235857 0%, #3B8A7F 100%);
            opacity: 0.45;
            border-radius: 18px;
            z-index: 0;
        }

        body::before {
            top: -60px;
            right: -60px;
            transform: rotate(20deg);
        }

        body::after {
            bottom: -60px;
            left: -60px;
            transform: rotate(20deg);
        }

        .login-wrapper {
            display: flex;
            width: 900px;
            max-width: 96vw;
            min-height: 560px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(35, 88, 87, 0.22);
            position: relative;
            z-index: 1;
        }

        /* ── LEFT PANEL ─────────────────────────────────────── */
        .login-left {
            flex: 0 0 45%;
            position: relative;
            background: linear-gradient(145deg, #1a4241 0%, #235857 45%, #3B8A7F 100%);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 2.5rem;
            overflow: hidden;
            border-radius: 16px;
            margin: 12px;
        }

        /* bokeh circles */
        .bokeh {
            position: absolute;
            border-radius: 50%;
            filter: blur(0);
            opacity: 0.18;
            background: rgba(255, 255, 255, 0.9);
        }

        .bokeh-1 {
            width: 180px;
            height: 180px;
            top: -40px;
            left: -40px;
            opacity: 0.12;
        }

        .bokeh-2 {
            width: 110px;
            height: 110px;
            top: 30px;
            left: 120px;
            opacity: 0.15;
        }

        .bokeh-3 {
            width: 70px;
            height: 70px;
            top: 20px;
            right: 40px;
            opacity: 0.10;
        }

        .bokeh-4 {
            width: 200px;
            height: 200px;
            top: 180px;
            left: -60px;
            opacity: 0.08;
        }

        .bokeh-5 {
            width: 90px;
            height: 90px;
            top: 200px;
            right: 10px;
            opacity: 0.14;
        }

        .bokeh-6 {
            width: 130px;
            height: 130px;
            bottom: 80px;
            left: 60px;
            opacity: 0.10;
        }

        .bokeh-7 {
            width: 55px;
            height: 55px;
            bottom: 160px;
            right: 30px;
            opacity: 0.16;
        }

        .bokeh-8 {
            width: 40px;
            height: 40px;
            bottom: 60px;
            right: 90px;
            opacity: 0.18;
        }

        /* sparkle dots */
        .sparkle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 50%;
            animation: twinkle 3s ease-in-out infinite;
        }

        @keyframes twinkle {

            0%,
            100% {
                opacity: 0.3;
                transform: scale(1);
            }

            50% {
                opacity: 1;
                transform: scale(1.6);
            }
        }

        .sp-1 {
            top: 15%;
            left: 55%;
            animation-delay: 0s;
        }

        .sp-2 {
            top: 30%;
            left: 75%;
            animation-delay: 0.5s;
        }

        .sp-3 {
            top: 50%;
            left: 60%;
            animation-delay: 1s;
        }

        .sp-4 {
            top: 65%;
            left: 82%;
            animation-delay: 1.5s;
        }

        .sp-5 {
            top: 80%;
            left: 50%;
            animation-delay: 0.8s;
        }

        .sp-6 {
            top: 10%;
            left: 40%;
            animation-delay: 1.2s;
        }

        .sp-7 {
            top: 42%;
            left: 30%;
            animation-delay: 0.3s;
        }

        .left-logo {
            position: absolute;
            top: 1.8rem;
            left: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 2;
        }

        .left-logo img {
            height: 34px;
            filter: brightness(0) invert(1);
            opacity: 0.9;
        }

        .left-logo-text {
            font-size: 0.8rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.9);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .left-content {
            position: relative;
            z-index: 2;
        }

        .left-content h1 {
            color: #fff;
            font-size: 1.95rem;
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 1.1rem;
        }

        .left-content p {
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.85rem;
            line-height: 1.65;
            max-width: 280px;
        }

        /* ── RIGHT PANEL ────────────────────────────────────── */
        .login-right {
            flex: 1;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .right-top-bar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 1.8rem 2.5rem 0;
            gap: 0.75rem;
        }

        .right-top-bar span {
            font-size: 0.82rem;
            color: #9ca3af;
        }

        .right-form-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2rem 3rem 3.5rem;
        }

        .form-group-custom {
            margin-bottom: 1.4rem;
        }

        .form-label-custom {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 0.45rem;
            display: block;
        }

        .form-control-custom {
            width: 100%;
            border: 1.5px solid #e5e7eb;
            border-radius: 6px;
            padding: 0.65rem 0.9rem;
            font-size: 0.9rem;
            color: #374151;
            background: #fff;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .form-control-custom:focus {
            border-color: #235857;
            box-shadow: 0 0 0 3px rgba(35, 88, 87, 0.12);
        }

        .form-control-custom::placeholder {
            color: #d1d5db;
            font-size: 0.88rem;
        }

        .btn-login {
            background: #235857;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 0.7rem 2rem;
            font-size: 0.92rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-login:hover {
            background: #1a4241;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(35, 88, 87, 0.35);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login .arrow {
            font-size: 1rem;
            transition: transform 0.2s;
        }

        .btn-login:hover .arrow {
            transform: translateX(3px);
        }

        .forgot-link {
            font-size: 0.8rem;
            color: #9ca3af;
            text-decoration: none;
            margin-top: 0.9rem;
            display: block;
            text-align: right;
        }

        .forgot-link:hover {
            color: #235857;
        }

        .btn-actions-row {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .alert-custom {
            border-radius: 8px;
            padding: 0.65rem 1rem;
            font-size: 0.84rem;
            margin-bottom: 1.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-danger-custom {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-warning-custom {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .login-wrapper {
                flex-direction: column;
                min-height: auto;
                border-radius: 16px;
            }

            .login-left {
                flex: 0 0 200px;
                border-radius: 12px;
                margin: 10px 10px 0;
                padding: 1.8rem;
            }

            .login-left .left-content h1 {
                font-size: 1.4rem;
            }

            .right-form-wrap {
                padding: 1.5rem 1.5rem 2rem;
            }

            .right-top-bar {
                padding: 1.2rem 1.5rem 0;
            }
        }
    </style>
</head>

<body>
    <div class="login-wrapper">

        <!-- ── LEFT PANEL ─────────────────────────── -->
        <div class="login-left">
            <!-- Bokeh circles -->
            <div class="bokeh bokeh-1"></div>
            <div class="bokeh bokeh-2"></div>
            <div class="bokeh bokeh-3"></div>
            <div class="bokeh bokeh-4"></div>
            <div class="bokeh bokeh-5"></div>
            <div class="bokeh bokeh-6"></div>
            <div class="bokeh bokeh-7"></div>
            <div class="bokeh bokeh-8"></div>
            <!-- Sparkle dots -->
            <div class="sparkle sp-1"></div>
            <div class="sparkle sp-2"></div>
            <div class="sparkle sp-3"></div>
            <div class="sparkle sp-4"></div>
            <div class="sparkle sp-5"></div>
            <div class="sparkle sp-6"></div>
            <div class="sparkle sp-7"></div>

            <!-- Logo -->
            <div class="left-logo">
                <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo">
                <span class="left-logo-text">Suyogya Saccos</span>
            </div>

            <!-- Hero Text -->
            <div class="left-content">
                <h1>Event Management<br>Made Simple</h1>
                <p>Manage events, track member attendance, and generate comprehensive reports — all from one
                    powerful dashboard.</p>
            </div>
        </div>

        <!-- ── RIGHT PANEL ────────────────────────── -->
        <div class="login-right">
            <div class="right-top-bar">
                <span>Admin Portal</span>
            </div>

            <div class="right-form-wrap">
                <?php if (isset($_GET['timeout'])): ?>
                    <div class="alert-custom alert-warning-custom">
                        <i class="fas fa-clock"></i>
                        Your session expired due to inactivity. Please log in again.
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert-custom alert-danger-custom">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                    <div class="form-group-custom">
                        <label class="form-label-custom" for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control-custom"
                            placeholder="Username" required autofocus autocomplete="username">
                    </div>

                    <div class="form-group-custom">
                        <label class="form-label-custom" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control-custom"
                            placeholder="Password" required autocomplete="current-password">
                    </div>

                    <div class="btn-actions-row">
                        <button type="submit" class="btn-login">
                            Login <span class="arrow">→</span>
                        </button>
                        <a href="#" class="forgot-link"
                            onclick="Swal.fire({title: 'Forgot Password?', text: 'Please contact the system administrator or database operator to reset your login credentials.', icon: 'info', confirmButtonColor: '#235857'}); return false;">Forgot
                            password?</a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</body>

</html>