<?php

session_start();

require_once "config.php";
require_once "helpers.php";

/*
|--------------------------------------------------------------------------
| REDIRECT LOGGED-IN USERS
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION["user_id"])
    || tryRememberLogin($conn)
) {
    $conn->close();

    header("Location: Dashboard.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GET SESSION MESSAGES
|--------------------------------------------------------------------------
*/

$error =
    $_SESSION["error"]
    ?? "";

$success =
    $_SESSION["success"]
    ?? "";

$lockUntil =
    (int) (
        $_SESSION["lock_until_timestamp"]
        ?? 0
    );

/*
|--------------------------------------------------------------------------
| REMOVE EXPIRED ACCOUNT LOCK
|--------------------------------------------------------------------------
*/

if (
    $lockUntil > 0
    && time() >= $lockUntil
) {
    $lockUntil = 0;

    unset(
        $_SESSION["lock_until_timestamp"]
    );
}

/*
|--------------------------------------------------------------------------
| REMOVE ONE-TIME SESSION VALUES
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION["error"],
    $_SESSION["success"],
    $_SESSION["old_login_identifier"]
);

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Register</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="css/style.css">

    <style>
        /*
        |--------------------------------------------------------------------------
        | NOTICES
        |--------------------------------------------------------------------------
        */

        .notice {
            padding: 10px 12px;
            border-radius: 8px;
            margin: 12px 0;
            color: #ffffff;
            font-size: 0.85rem;
        }

        .error {
            background: #a82b36;
        }

        .success {
            background: #167a4b;
        }

        /*
        |--------------------------------------------------------------------------
        | PASSWORD MESSAGES
        |--------------------------------------------------------------------------
        */

        .strength {
            min-height: 18px;
            margin: -12px 0 12px;
            color: #ffffff;
            font-size: 0.82rem;
        }

        /*
        |--------------------------------------------------------------------------
        | ACCOUNT LOCK
        |--------------------------------------------------------------------------
        */

        .lock-box {
            padding: 10px;
            border-radius: 8px;
            background: #8d2632;
            color: #ffffff;
            margin-bottom: 12px;
            text-align: center;
        }

        .login-submit:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        /*
        |--------------------------------------------------------------------------
        | HIDDEN AUTOFILL TRAP
        |--------------------------------------------------------------------------
        */

        .autofill-trap {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            opacity: 0;
            pointer-events: none;
            left: -9999px;
            top: -9999px;
        }

        /*
        |--------------------------------------------------------------------------
        | PASSWORD EYE ICON
        |--------------------------------------------------------------------------
        */

        .input-box {
            position: relative;
        }

        .input-box input[type="password"],
        .input-box input.password-visible {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 5px;
            z-index: 10;

            display: flex;
            align-items: center;
            justify-content: center;

            width: 38px;
            height: 38px;

            padding: 0;
            border: none;
            border-radius: 50%;
            outline: none;

            background: transparent;
            color: #ffffff;

            font-size: 17px;
            line-height: 1;
            cursor: pointer;

            transform: translateY(-50%);
        }

        .password-toggle:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .password-toggle:focus-visible {
            outline: 2px solid #ffffff;
            outline-offset: 1px;
        }

        .password-toggle i {
            pointer-events: none;
        }
    </style>

</head>

<body>

    <section>

        <!-- =====================================================
             LOGIN FORM
        ====================================================== -->

        <div class="login-box">

            <form id="loginForm" action="process_login.php" method="POST" autocomplete="off">

                <h2>Login</h2>

                <?php if (!empty($error)): ?>

                    <div class="notice error">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>

                <?php if (!empty($success)): ?>

                    <div class="notice success">

                        <?= htmlspecialchars($success) ?>

                    </div>

                <?php endif; ?>

                <?php if ($lockUntil > 0): ?>

                    <div class="lock-box">

                        Account locked:

                        <strong id="lockCountdown">
                            15:00
                        </strong>

                    </div>

                <?php endif; ?>

                <!-- AUTOFILL TRAP -->

                <div class="autofill-trap" aria-hidden="true">

                    <input type="text" name="fake_login_identifier" autocomplete="username" tabindex="-1">

                    <input type="password" name="fake_login_password" autocomplete="current-password" tabindex="-1">

                </div>

                <!-- LOGIN IDENTIFIER -->

                <div class="input-box">

                    <span class="icon">

                        <ion-icon name="mail-outline"></ion-icon>

                    </span>

                    <input type="text" name="login_identifier" id="loginEmail" value="" autocomplete="off"
                        autocapitalize="none" spellcheck="false" maxlength="100" required>

                    <label for="loginEmail">
                        Username or Email
                    </label>

                </div>

                <!-- LOGIN PASSWORD -->

                <div class="input-box">

                    <span class="icon">

                        <ion-icon name="lock-closed-outline"></ion-icon>

                    </span>

                    <input type="password" name="login_password" id="loginPassword" autocomplete="current-password"
                        required>

                    <label for="loginPassword">
                        Password
                    </label>

                    <button type="button" class="password-toggle" data-target="loginPassword" aria-label="Show password"
                        aria-pressed="false">

                        <i class="fa-solid fa-eye"></i>

                    </button>

                </div>

                <!-- REMEMBER ME -->

                <div class="remember-forgot">

                    <label>

                        <input type="checkbox" name="remember_me" value="1" id="rememberMe">

                        Remember me

                    </label>

                    <a href="forgot_password.php">
                        Forgot Password?
                    </a>

                </div>

                <button type="submit" id="loginSubmit" class="btn login-submit" <?= $lockUntil > 0 ? "disabled" : "" ?>>

                    <?= $lockUntil > 0
                        ? "Account Locked"
                        : "Login" ?>

                </button>

                <div class="register-link">

                    <p>

                        Don't have an account?

                        <a href="#" class="register-link">
                            Register
                        </a>

                    </p>

                </div>

            </form>

        </div>

        <!-- =====================================================
             REGISTRATION FORM
        ====================================================== -->

        <div class="sign-up">

            <form id="registerForm" action="process_register.php" method="POST" autocomplete="off">

                <h2>Register</h2>

                <!-- AUTOFILL TRAP -->

                <div class="autofill-trap" aria-hidden="true">

                    <input type="text" name="fake_username" autocomplete="username" tabindex="-1">

                    <input type="password" name="fake_password" autocomplete="current-password" tabindex="-1">

                </div>

                <!-- FULL NAME -->

                <div class="input-box">

                    <input type="text" name="full_name" id="regFullName" autocomplete="off" autocapitalize="words"
                        spellcheck="false" maxlength="100" required>

                    <label for="regFullName">
                        Full Name
                    </label>

                </div>

                <!-- USERNAME -->

                <div class="input-box">

                    <input type="text" name="username" id="regUsername" autocomplete="off" autocapitalize="none"
                        spellcheck="false" maxlength="50" required>

                    <label for="regUsername">
                        Username
                    </label>

                </div>

                <!-- EMAIL -->

                <div class="input-box">

                    <input type="email" name="email" id="regEmail" autocomplete="off" autocapitalize="none"
                        spellcheck="false" maxlength="100" required>

                    <label for="regEmail">
                        Email
                    </label>

                </div>

                <!-- PASSWORD -->

                <div class="input-box">

                    <input type="password" name="password" id="regPassword" autocomplete="new-password" minlength="8"
                        required>

                    <label for="regPassword">
                        Password
                    </label>

                    <button type="button" class="password-toggle" data-target="regPassword" aria-label="Show password"
                        aria-pressed="false">

                        <i class="fa-solid fa-eye"></i>

                    </button>

                </div>

                <div id="strengthMessage" class="strength"></div>

                <!-- CONFIRM PASSWORD -->

                <div class="input-box">

                    <input type="password" name="confirm_password" id="confirmPassword" autocomplete="new-password"
                        minlength="8" required>

                    <label for="confirmPassword">
                        Confirm Password
                    </label>

                    <button type="button" class="password-toggle" data-target="confirmPassword"
                        aria-label="Show password" aria-pressed="false">

                        <i class="fa-solid fa-eye"></i>

                    </button>

                </div>

                <div id="confirmMessage" class="strength"></div>

                <!-- TERMS -->

                <div class="remember-forgot">

                    <label>

                        <input type="checkbox" name="terms" value="1" required>

                        I agree to the terms and conditions

                    </label>

                </div>

                <button type="submit" class="btn">
                    Register
                </button>

                <div class="register-link">

                    <p>

                        Already have an account?

                        <a href="#" class="register-link">
                            Login
                        </a>

                    </p>

                </div>

            </form>

        </div>

    </section>

    <script>
        window.LOCK_UNTIL =
            <?= json_encode($lockUntil) ?>;
    </script>

    <script type="module" src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.esm.js"></script>

    <script nomodule src="https://unpkg.com/ionicons@8.0.13/dist/ionicons/ionicons.js"></script>

    <script src="js/main.js"></script>

    <script src="js/password-toggle.js"></script>

</body>

</html>