<?php

require_once "auth.php";

/*
|--------------------------------------------------------------------------
| GET SESSION MESSAGES
|--------------------------------------------------------------------------
*/

$error =
    $_SESSION["password_error"]
    ?? "";

$success =
    $_SESSION["password_success"]
    ?? "";

/*
|--------------------------------------------------------------------------
| REMOVE ONE-TIME SESSION MESSAGES
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION["password_error"],
    $_SESSION["password_success"]
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Change Password</title>

    <link rel="stylesheet" href="css/change-password.css?v=1">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
    .message {
        width: 100%;
        padding: 10px 12px;
        margin-bottom: 15px;
        border-radius: 8px;
        color: #ffffff;
        font-size: 0.9rem;
        line-height: 1.4;
    }

    .message.error {
        background: #9b2934;
    }

    .message.success {
        background: #176f4b;
    }

    .password-strength {
        margin-top: -18px;
        margin-bottom: 16px;
        color: #ffffff;
        font-size: 0.85rem;
    }

    .password-strength.weak {
        color: #ff5b67;
    }

    .password-strength.fair {
        color: #ffd15b;
    }

    .password-strength.strong {
        color: #44ff9a;
    }

    .password-match {
        margin-top: -18px;
        margin-bottom: 16px;
        color: #ffffff;
        font-size: 0.85rem;
    }

    .password-match.match {
        color: #44ff9a;
    }

    .password-match.no-match {
        color: #ff5b67;
    }
    </style>

</head>

<body>

    <div class="container">

        <div class="card">

            <h2>Change Password</h2>

            <?php if (!empty($error)): ?>

            <div class="message error">

                <?= htmlspecialchars($error) ?>

            </div>

            <?php endif; ?>

            <?php if (!empty($success)): ?>

            <div class="message success">

                <?= htmlspecialchars($success) ?>

            </div>

            <?php endif; ?>

            <form id="changePasswordForm" action="process_change_password.php" method="POST" autocomplete="off">

                <div class="input-box">

                    <i class="fa-solid fa-lock input-icon"></i>

                    <input type="password" id="currentPassword" name="current_password"
                        placeholder="Enter current password" autocomplete="current-password" required>

                    <button type="button" class="password-toggle" data-target="currentPassword"
                        aria-label="Show current password" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>

                </div>
                <div class="input-box">

                    <i class="fa-solid fa-key input-icon"></i>

                    <input type="password" id="newPassword" name="new_password" placeholder="Enter new password"
                        minlength="8" autocomplete="new-password" required>

                    <button type="button" class="password-toggle" data-target="newPassword"
                        aria-label="Show new password" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>

                </div>
                <div class="input-box">

                    <i class="fa-solid fa-check input-icon"></i>

                    <input type="password" id="confirmNewPassword" name="confirm_password"
                        placeholder="Confirm new password" minlength="8" autocomplete="new-password" required>

                    <button type="button" class="password-toggle" data-target="confirmNewPassword"
                        aria-label="Show confirmed password" aria-pressed="false">
                        <i class="fa-solid fa-eye"></i>
                    </button>

                </div>
                <!-- SUBMIT BUTTON -->

                <button type="submit">

                    Update Password

                </button>

                <!-- BACK BUTTON -->

                <a href="Dashboard.php" class="back">

                    <i class="bx bx-arrow-back"></i>

                    Back to Dashboard

                </a>

            </form>

        </div>

    </div>

    <script src="js/change-password.js"></script>

    <script src="js/password-toggle.js"></script>

</body>

</html>