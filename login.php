<?php
require_once 'config/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: admin/dashboard.php');
    exit;
}

$error = '';
$active_tab = 'login'; // Default to login for Event Management System

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $form_action = $_POST['form_action'] ?? 'login';

    if (!validateCsrfToken($csrf_token)) {
        $error = "Invalid CSRF token! Please try again.";
    } else {
        if ($form_action === 'signup') {
            $active_tab = 'signup';
            // Self-registration notice for admin security
            $error = "Self-registration is restricted. Please contact the administrator for event operator credentials.";
        } else {
            $active_tab = 'login';
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $error = "Please enter both username and password.";
            } else {
                $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_role'] = $user['role'];
                    $_SESSION['last_active'] = time();
                    header('Location: admin/dashboard.php');
                    exit;
                } else {
                    $error = "Invalid username or password!";
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
    <title><?= $active_tab === 'signup' ? 'Create an account' : 'Log in' ?> &bull; SUYOGYA SACCOS Event Management</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --teal-bg: #073844;
            --teal-gradient-start: #083c4a;
            --teal-gradient-end: #05303a;
            --btn-teal: #083844;
            --btn-teal-hover: #05262f;
            --body-bg: #eceef2;
            --input-bg: #f4f6f8;
            --input-border: #edf0f3;
            --input-focus-border: #083844;
            --text-heading: #111827;
            --text-label: #374151;
            --text-muted: #6b7280;
            --text-placeholder: #9ca3af;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--body-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: var(--text-heading);
            -webkit-font-smoothing: antialiased;
        }

        /* ── CARD CONTAINER ────────────────────────────────────────── */
        .auth-card {
            display: flex;
            width: 1020px;
            max-width: 100%;
            min-height: 580px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 45px rgba(8, 48, 58, 0.12), 0 4px 12px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            position: relative;
        }

        /* ── LEFT PANEL (WHITE FORM) ───────────────────────────────── */
        .auth-left {
            flex: 1.05;
            padding: 2.75rem 3.25rem 2.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: #ffffff;
            position: relative;
            z-index: 2;
        }

        /* Brand Logo */
        .brand-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--btn-teal);
            user-select: none;
            margin-bottom: 1.5rem;
        }

        .brand-logo-img {
            height: 38px;
            width: auto;
            object-fit: contain;
        }

        .brand-text-block {
            display: flex;
            flex-direction: column;
        }

        .brand-name {
            font-size: 1.12rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #083844;
            line-height: 1.2;
        }

        .brand-tagline {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
        }

        /* Form Header */
        .form-heading {
            font-size: 1.7rem;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.02em;
            margin-bottom: 1.5rem;
        }

        /* Alert notifications */
        .alert-box {
            padding: 0.65rem 0.9rem;
            border-radius: 8px;
            font-size: 0.82rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }

        .alert-box-error {
            background-color: #fef2f2;
            border: 1px solid #fee2e2;
            color: #b91c1c;
        }

        .alert-box-warning {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            color: #b45309;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.05rem;
        }

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-label);
            margin-bottom: 0.38rem;
        }

        .input-box-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-input {
            width: 100%;
            padding: 0.72rem 1rem;
            background-color: var(--input-bg);
            border: 1.5px solid var(--input-border);
            border-radius: 8px;
            font-size: 0.88rem;
            color: #1f2937;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-input::placeholder {
            color: var(--text-placeholder);
            font-size: 0.86rem;
        }

        .form-input:focus {
            background-color: #ffffff;
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px rgba(8, 56, 68, 0.1);
        }

        .input-toggle-btn {
            position: absolute;
            right: 0.85rem;
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            transition: color 0.2s;
        }

        .input-toggle-btn:hover {
            color: #083844;
        }

        /* Terms / Remember Row */
        .form-options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0.35rem;
            margin-bottom: 1.25rem;
            font-size: 0.8rem;
        }

        .custom-checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            color: #4b5563;
            cursor: pointer;
            user-select: none;
            font-size: 0.8rem;
        }

        .custom-checkbox-label input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            border: 1.5px solid #cbd5e1;
            border-radius: 4px;
            cursor: pointer;
            background-color: #ffffff;
            position: relative;
            outline: none;
            flex-shrink: 0;
            transition: all 0.15s;
        }

        .custom-checkbox-label input[type="checkbox"]:checked {
            background-color: #083844;
            border-color: #083844;
        }

        .custom-checkbox-label input[type="checkbox"]:checked::after {
            content: '';
            position: absolute;
            left: 4px;
            top: 1.5px;
            width: 4px;
            height: 8px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .forgot-link {
            color: #083844;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        /* Main Submit Button */
        .btn-main {
            width: 100%;
            padding: 0.78rem 1rem;
            background-color: var(--btn-teal);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 0.92rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(8, 56, 68, 0.22);
        }

        .btn-main:hover {
            background-color: var(--btn-teal-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(8, 56, 68, 0.3);
        }

        .btn-main:active {
            transform: translateY(0);
        }

        /* Or Divider */
        .divider-row {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.15rem 0;
            color: #9ca3af;
            font-size: 0.76rem;
        }

        .divider-row::before,
        .divider-row::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #f1f3f5;
        }

        .divider-row span {
            padding: 0 0.75rem;
        }

        /* Social Buttons */
        .social-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem;
            margin-bottom: 1.35rem;
        }

        .btn-social-auth {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            padding: 0.65rem 0.85rem;
            background-color: #ffffff;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            color: #374151;
            font-size: 0.84rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .btn-social-auth:hover {
            background-color: #f9fafb;
            border-color: #d1d5db;
        }

        .btn-social-auth svg {
            width: 17px;
            height: 17px;
            flex-shrink: 0;
        }

        /* Footer switch link */
        .auth-bottom-switch {
            text-align: center;
            font-size: 0.8rem;
            color: #6b7280;
            padding-top: 0.5rem;
        }

        .auth-bottom-switch a {
            color: #083844;
            font-weight: 700;
            text-decoration: none;
            margin-left: 0.25rem;
        }

        .auth-bottom-switch a:hover {
            text-decoration: underline;
        }

        /* ── RIGHT PANEL (DARK TEAL HERO) ──────────────────────────── */
        .auth-right {
            flex: 0.95;
            background: linear-gradient(155deg, #073844 0%, #052c36 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem 2.5rem;
            position: relative;
            overflow: hidden;
            user-select: none;
        }

        /* Corner Mosaic Tiles */
        .mosaic-top-right {
            position: absolute;
            top: 22px;
            right: 22px;
            display: grid;
            grid-template-columns: repeat(3, 26px);
            gap: 12px;
            pointer-events: none;
        }

        .mosaic-bottom-left {
            position: absolute;
            bottom: 22px;
            left: 22px;
            display: grid;
            grid-template-columns: repeat(2, 24px);
            gap: 10px;
            pointer-events: none;
        }

        .tile-sq {
            height: 26px;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.09);
        }

        .tile-sq.dim {
            background: rgba(255, 255, 255, 0.04);
        }

        .tile-sq.lit {
            background: rgba(255, 255, 255, 0.16);
        }

        .tile-sq.empty {
            background: transparent;
        }

        /* Mockup Cards Canvas */
        .cards-mockup-wrapper {
            position: relative;
            width: 100%;
            max-width: 350px;
            height: 245px;
            margin-bottom: 2rem;
            z-index: 2;
        }

        /* 1. Event Analytics Floating Card */
        .card-analytics {
            position: absolute;
            top: 0;
            left: 0;
            width: 295px;
            background: #ffffff;
            border-radius: 14px;
            padding: 1.1rem 1.25rem 0.9rem;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.28), 0 4px 10px rgba(0, 0, 0, 0.12);
        }

        .analytics-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.85rem;
        }

        .analytics-title {
            font-size: 0.94rem;
            font-weight: 700;
            color: #1f2937;
        }

        .analytics-pills {
            display: flex;
            align-items: center;
            background: #f3f4f6;
            border-radius: 20px;
            padding: 2px 3px;
        }

        .analytics-pill-item {
            font-size: 0.62rem;
            font-weight: 600;
            color: #6b7280;
            padding: 2px 6px;
            border-radius: 12px;
            text-decoration: none;
        }

        .analytics-pill-item.active {
            background: #ffffff;
            color: #083844;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .chart-wrap {
            width: 100%;
            height: 72px;
        }

        .chart-days {
            display: flex;
            justify-content: space-between;
            padding: 0.35rem 0.2rem 0;
            border-top: 1px solid #f3f4f6;
            margin-top: 0.2rem;
        }

        .day-label {
            font-size: 0.62rem;
            font-weight: 700;
            color: #9ca3af;
        }

        /* 2. Donut Attendance Metric Card (Attendance 84%) */
        .card-donut-metric {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 155px;
            height: 155px;
            background: #ffffff;
            border-radius: 18px;
            padding: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.32), 0 6px 14px rgba(0, 0, 0, 0.15);
            z-index: 3;
        }

        .donut-container {
            position: relative;
            width: 110px;
            height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .donut-svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }

        .donut-track {
            fill: none;
            stroke: #edf0f3;
            stroke-width: 14;
        }

        .donut-bar {
            fill: none;
            stroke: #083844;
            stroke-width: 14;
            stroke-linecap: round;
            stroke-dasharray: 283;
            stroke-dashoffset: 45; /* ~84% turnout */
        }

        .donut-label-box {
            position: absolute;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .donut-sub {
            font-size: 0.7rem;
            color: #6b7280;
            font-weight: 500;
        }

        .donut-stat {
            font-size: 1.25rem;
            font-weight: 800;
            color: #083844;
            line-height: 1.1;
        }

        /* Right Hero Text */
        .right-hero-wrap {
            text-align: center;
            z-index: 2;
        }

        .right-title {
            color: #ffffff;
            font-size: 1.22rem;
            font-weight: 700;
            margin-bottom: 0.6rem;
            letter-spacing: -0.01em;
        }

        .right-desc {
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.8rem;
            line-height: 1.55;
            max-width: 320px;
            margin: 0 auto;
        }

        /* Responsive */
        @media (max-width: 880px) {
            .auth-card {
                flex-direction: column;
                max-width: 460px;
            }

            .auth-right {
                order: -1;
                padding: 2.25rem 1.5rem;
            }

            .cards-mockup-wrapper {
                max-width: 290px;
                height: 220px;
                margin-bottom: 1.5rem;
            }

            .card-analytics {
                width: 235px;
            }

            .card-donut-metric {
                width: 130px;
                height: 130px;
            }

            .donut-container {
                width: 95px;
                height: 95px;
            }

            .auth-left {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>

<body>

    <div class="auth-card">

        <!-- ── LEFT PANEL: FORM ───────────────────────────────────────────── -->
        <div class="auth-left">

            <!-- Logo with SUYOGYA SACCOS Official Brand -->
            <a href="login.php" class="brand-logo" title="SUYOGYA SACCOS Event Management">
                <img src="<?= BASE_URL ?>assets/img/logo.png" alt="SUYOGYA SACCOS" class="brand-logo-img">
                <div class="brand-text-block">
                    <span class="brand-name">SUYOGYA SACCOS</span>
                    <span class="brand-tagline">Event Management</span>
                </div>
            </a>

            <!-- Form Body -->
            <div class="form-container-inner">

                <h1 class="form-heading" id="formHeading">
                    <?= $active_tab === 'signup' ? 'Create an account' : 'Log in' ?>
                </h1>

                <!-- Timeout Notice -->
                <?php if (isset($_GET['timeout'])): ?>
                    <div class="alert-box alert-box-warning">
                        <i class="fas fa-clock"></i>
                        <span>Session expired. Please log in again.</span>
                    </div>
                <?php endif; ?>

                <!-- Error Notice -->
                <?php if (!empty($error)): ?>
                    <div class="alert-box alert-box-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" id="mainAuthForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
                    <input type="hidden" name="form_action" id="formActionInput" value="<?= htmlspecialchars($active_tab) ?>">

                    <!-- Name Field (for Create Account) -->
                    <div class="form-group" id="nameFieldWrap" style="<?= $active_tab === 'signup' ? '' : 'display: none;' ?>">
                        <label class="form-label" for="userName">Name</label>
                        <div class="input-box-wrapper">
                            <input type="text" id="userName" name="name" class="form-input" placeholder="Enter your full name" autocomplete="name">
                        </div>
                    </div>

                    <!-- Email / Username Field -->
                    <div class="form-group">
                        <label class="form-label" for="emailOrUserInput" id="userFieldLabel">
                            <?= $active_tab === 'signup' ? 'Email' : 'Username' ?>
                        </label>
                        <div class="input-box-wrapper">
                            <input type="text" id="emailOrUserInput" name="username" class="form-input" 
                                placeholder="<?= $active_tab === 'signup' ? 'Enter your email' : 'Enter your username' ?>" 
                                required autofocus autocomplete="username">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="form-group">
                        <label class="form-label" for="passwordField">Password</label>
                        <div class="input-box-wrapper">
                            <input type="password" id="passwordField" name="password" class="form-input" 
                                placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="input-toggle-btn" id="togglePasswordBtn" title="Toggle password visibility">
                                <i class="far fa-eye-slash" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Terms Checkbox or Remember Me -->
                    <div class="form-options-row">
                        <label class="custom-checkbox-label">
                            <input type="checkbox" id="termsCheck" name="terms" checked>
                            <span id="checkboxLabelText">
                                <?= $active_tab === 'signup' ? 'I agree to all the Terms &amp; Conditions' : 'Remember me for 30 days' ?>
                            </span>
                        </label>
                        <a href="#" class="forgot-link" id="forgotLink" style="<?= $active_tab === 'signup' ? 'display: none;' : '' ?>">Forgot password?</a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-main" id="submitBtn">
                        <span id="btnLabel"><?= $active_tab === 'signup' ? 'Sign up' : 'Log in' ?></span>
                    </button>
                </form>

                <!-- Or Divider -->
                <div class="divider-row">
                    <span>Or</span>
                </div>

                <!-- Social / Directory Sign In Buttons -->
                <div class="social-row">
                    <button type="button" class="btn-social-auth" onclick="ssoNotice('Google')">
                        <svg viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                        </svg>
                        <span>Google</span>
                    </button>

                    <button type="button" class="btn-social-auth" onclick="ssoNotice('Facebook')">
                        <svg viewBox="0 0 24 24" fill="#1877F2">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                        <span>Facebook</span>
                    </button>
                </div>

            </div>

            <!-- Footer Switcher -->
            <div class="auth-bottom-switch">
                <span id="footerPrompt"><?= $active_tab === 'signup' ? 'Already have an account?' : "Don't have an account?" ?></span>
                <a href="#" id="toggleLink" onclick="switchMode(event)"><?= $active_tab === 'signup' ? 'Log in' : 'Sign up' ?></a>
            </div>

        </div>


        <!-- ── RIGHT PANEL: GRAPHIC & MOCKUP ─────────────────────────────── -->
        <div class="auth-right">

            <!-- Corner Mosaic Tiles (top-right) -->
            <div class="mosaic-top-right">
                <div class="tile-sq lit"></div>
                <div class="tile-sq"></div>
                <div class="tile-sq dim"></div>
                <div class="tile-sq empty"></div>
                <div class="tile-sq lit"></div>
                <div class="tile-sq"></div>
                <div class="tile-sq empty"></div>
                <div class="tile-sq"></div>
                <div class="tile-sq lit"></div>
            </div>

            <!-- Corner Mosaic Tiles (bottom-left) -->
            <div class="mosaic-bottom-left">
                <div class="tile-sq"></div>
                <div class="tile-sq lit"></div>
                <div class="tile-sq lit"></div>
                <div class="tile-sq dim"></div>
            </div>

            <!-- Center Visual Mockup Cards -->
            <div class="cards-mockup-wrapper">

                <!-- 1. Floating Attendance Analytics Card -->
                <div class="card-analytics">
                    <div class="analytics-top">
                        <span class="analytics-title">Attendance</span>
                        <div class="analytics-pills">
                            <span class="analytics-pill-item active">Weekly</span>
                            <span class="analytics-pill-item">Monthly</span>
                            <span class="analytics-pill-item">Yearly</span>
                        </div>
                    </div>

                    <div class="chart-wrap">
                        <svg viewBox="0 0 250 75" style="width: 100%; height: 100%; overflow: visible;">
                            <defs>
                                <linearGradient id="primaryArea" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#083844" stop-opacity="0.22"/>
                                    <stop offset="100%" stop-color="#083844" stop-opacity="0.0"/>
                                </linearGradient>
                                <linearGradient id="secondaryArea" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.14"/>
                                    <stop offset="100%" stop-color="#38bdf8" stop-opacity="0.0"/>
                                </linearGradient>
                            </defs>

                            <!-- Guidelines -->
                            <line x1="0" y1="20" x2="250" y2="20" stroke="#f1f5f9" stroke-width="1" />
                            <line x1="0" y1="45" x2="250" y2="45" stroke="#f1f5f9" stroke-width="1" />

                            <!-- Secondary Line (Total Registered) -->
                            <path d="M 10 52 Q 55 42 75 30 T 145 40 T 205 25 T 240 35" 
                                fill="none" stroke="#94a3b8" stroke-width="1.6" stroke-linecap="round" />
                            <path d="M 10 52 Q 55 42 75 30 T 145 40 T 205 25 T 240 35 L 240 75 L 10 75 Z" 
                                fill="url(#secondaryArea)" />

                            <!-- Primary Line (Actual Turnout) -->
                            <path d="M 10 60 Q 45 48 75 16 T 145 35 T 200 12 T 240 20" 
                                fill="none" stroke="#083844" stroke-width="2.2" stroke-linecap="round" />
                            <path d="M 10 60 Q 45 48 75 16 T 145 35 T 200 12 T 240 20 L 240 75 L 10 75 Z" 
                                fill="url(#primaryArea)" />

                            <circle cx="75" cy="16" r="3" fill="#083844" />
                        </svg>
                    </div>

                    <div class="chart-days">
                        <span class="day-label">MON</span>
                        <span class="day-label">TUE</span>
                        <span class="day-label">WED</span>
                        <span class="day-label">THU</span>
                    </div>
                </div>

                <!-- 2. Overlapping Donut Metric Card (Turnout 84%) -->
                <div class="card-donut-metric">
                    <div class="donut-container">
                        <svg class="donut-svg" viewBox="0 0 100 100">
                            <circle class="donut-track" cx="50" cy="50" r="38" />
                            <circle class="donut-bar" cx="50" cy="50" r="38" />
                        </svg>
                        <div class="donut-label-box">
                            <span class="donut-sub">Turnout</span>
                            <span class="donut-stat">84%</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Hero Text Tailored to Event Management -->
            <div class="right-hero-wrap">
                <h2 class="right-title">Smart way to manage your events</h2>
                <p class="right-desc">
                    Welcome to SUYOGYA SACCOS Event Management System! Efficiently organize events, track member attendance, and generate reports with ease.
                </p>
            </div>

        </div>

    </div>

    <!-- ── INTERACTION SCRIPT ─────────────────────────────────────────── -->
    <script>
        // Password Visibility Toggle
        const togglePasswordBtn = document.getElementById('togglePasswordBtn');
        const passwordField = document.getElementById('passwordField');
        const toggleIcon = document.getElementById('toggleIcon');

        togglePasswordBtn.addEventListener('click', function () {
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        });

        // Toggle between "Create an account" and "Log in"
        let currentMode = "<?= $active_tab === 'signup' ? 'signup' : 'login' ?>";

        function switchMode(e) {
            if (e) e.preventDefault();

            const formHeading = document.getElementById('formHeading');
            const nameWrap = document.getElementById('nameFieldWrap');
            const userLabel = document.getElementById('userFieldLabel');
            const userInput = document.getElementById('emailOrUserInput');
            const checkboxLabel = document.getElementById('checkboxLabelText');
            const forgotLink = document.getElementById('forgotLink');
            const btnLabel = document.getElementById('btnLabel');
            const footerPrompt = document.getElementById('footerPrompt');
            const toggleLink = document.getElementById('toggleLink');
            const formActionInput = document.getElementById('formActionInput');

            if (currentMode === 'signup') {
                // Switch to Log in
                currentMode = 'login';
                formActionInput.value = 'login';
                formHeading.textContent = 'Log in';
                nameWrap.style.display = 'none';
                userLabel.textContent = 'Username';
                userInput.placeholder = 'Enter your username';
                checkboxLabel.textContent = 'Remember me for 30 days';
                forgotLink.style.display = 'inline';
                btnLabel.textContent = 'Log in';
                footerPrompt.textContent = "Don't have an account?";
                toggleLink.textContent = 'Sign up';
                document.title = 'Log in • SUYOGYA SACCOS Event Management';
            } else {
                // Switch to Create an account
                currentMode = 'signup';
                formActionInput.value = 'signup';
                formHeading.textContent = 'Create an account';
                nameWrap.style.display = 'block';
                userLabel.textContent = 'Email';
                userInput.placeholder = 'Enter your email';
                checkboxLabel.textContent = 'I agree to all the Terms & Conditions';
                forgotLink.style.display = 'none';
                btnLabel.textContent = 'Sign up';
                footerPrompt.textContent = 'Already have an account?';
                toggleLink.textContent = 'Log in';
                document.title = 'Create an account • SUYOGYA SACCOS Event Management';
            }
        }

        // Forgot password notice
        document.getElementById('forgotLink').addEventListener('click', function (e) {
            e.preventDefault();
            Swal.fire({
                title: 'Forgot Password?',
                text: 'Please contact the system administrator to reset or retrieve your event portal credentials.',
                icon: 'info',
                confirmButtonColor: '#083844',
                confirmButtonText: 'Understood'
            });
        });

        // SSO notice
        function ssoNotice(provider) {
            Swal.fire({
                title: provider + ' SSO',
                text: provider + ' corporate login is available for authorized directory accounts.',
                icon: 'info',
                confirmButtonColor: '#083844',
                confirmButtonText: 'Got it'
            });
        }
    </script>
</body>

</html>