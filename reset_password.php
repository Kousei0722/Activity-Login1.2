<?php

declare(strict_types=1);

session_start();

require_once "config.php";

$resetVerified = !empty($_SESSION["reset_verified"]);
$resetUserId = (int) ($_SESSION["reset_user_id"] ?? 0);

if (!$resetVerified || $resetUserId <= 0) {
    header("Location: forgot_password.php");
    exit;
}

if (empty($_SESSION["reset_csrf_token"])) {
    $_SESSION["reset_csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submittedCsrfToken = (string) ($_POST["csrf_token"] ?? "");
    $sessionCsrfToken = (string) ($_SESSION["reset_csrf_token"] ?? "");

    $newPassword = (string) ($_POST["new_password"] ?? "");
    $confirmPassword = (string) ($_POST["confirm_password"] ?? "");

    if (
        $submittedCsrfToken === ""
        || $sessionCsrfToken === ""
        || !hash_equals($sessionCsrfToken, $submittedCsrfToken)
    ) {
        $error = "Your reset session is invalid or has expired. Please try again.";
    } elseif ($newPassword === "" || $confirmPassword === "") {
        $error = "Please complete all password fields.";
    } elseif (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (
        !preg_match("/[A-Z]/", $newPassword)
        || !preg_match("/[a-z]/", $newPassword)
        || !preg_match("/[0-9]/", $newPassword)
        || !preg_match("/[^A-Za-z0-9]/", $newPassword)
    ) {
        $error = "Use at least one uppercase letter, lowercase letter, number, and special character.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            $error = "Unable to secure the new password.";
        } else {
            $conn->begin_transaction();

            try {
                $updateSql = "
                    UPDATE users
                    SET
                        password = ?,
                        failed_attempts = 0,
                        lock_until = NULL,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ";

                $updateStmt = $conn->prepare($updateSql);

                if (!$updateStmt) {
                    throw new RuntimeException("Unable to prepare password update.");
                }

                $updateStmt->bind_param(
                    "si",
                    $passwordHash,
                    $resetUserId
                );

                if (!$updateStmt->execute()) {
                    $updateStmt->close();

                    throw new RuntimeException("Unable to update password.");
                }

                if ($updateStmt->affected_rows < 1) {
                    $updateStmt->close();

                    throw new RuntimeException("Account was not found.");
                }

                $updateStmt->close();

                $deleteTokenSql = "
                    DELETE FROM remember_tokens
                    WHERE user_id = ?
                ";

                $deleteTokenStmt = $conn->prepare($deleteTokenSql);

                if (!$deleteTokenStmt) {
                    throw new RuntimeException(
                        "Unable to invalidate remembered sessions."
                    );
                }

                $deleteTokenStmt->bind_param(
                    "i",
                    $resetUserId
                );

                if (!$deleteTokenStmt->execute()) {
                    $deleteTokenStmt->close();

                    throw new RuntimeException(
                        "Unable to invalidate remembered sessions."
                    );
                }

                $deleteTokenStmt->close();

                $conn->commit();

                unset(
                    $_SESSION["reset_user_id"],
                    $_SESSION["reset_email"],
                    $_SESSION["reset_code"],
                    $_SESSION["reset_expires"],
                    $_SESSION["reset_verified"],
                    $_SESSION["reset_csrf_token"]
                );

                $_SESSION["success"] =
                    "Password reset successful. You may now log in.";

                $conn->close();

                header("Location: Login.php");
                exit;
            } catch (Throwable $exception) {
                $conn->rollback();

                error_log(
                    "Password reset error: "
                        . $exception->getMessage()
                );

                $error =
                    "Password reset failed. Please try again.";
            }
        }
    }
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reset Password</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">

    <link rel="stylesheet" href="css/reset_password.css">

</head>

<body>

    <main class="page-wrapper">

        <section class="reset-card">

            <div class="card-icon">

                <i class="fa-solid fa-key" aria-hidden="true"></i>

            </div>

            <h1>Reset Password</h1>

            <p class="description">
                Create a strong new password for your account.
            </p>

            <?php if ($error !== ""): ?>

                <div class="message error" role="alert">

                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>

                    <span>

                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>

            <?php endif; ?>

            <form id="resetPasswordForm" action="reset_password.php" method="POST" autocomplete="off">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                                                                    (string) $_SESSION["reset_csrf_token"],
                                                                    ENT_QUOTES,
                                                                    "UTF-8"
                                                                ) ?>">

                <div class="input-field">

                    <span class="field-icon">

                        <i class="fa-solid fa-lock" aria-hidden="true"></i>

                    </span>

                    <input type="password" id="resetNewPassword" name="new_password" placeholder="New password"
                        minlength="8" autocomplete="new-password" required>

                    <button type="button" class="password-toggle" data-target="resetNewPassword"
                        aria-label="Show new password" aria-pressed="false">

                        <i class="fa-solid fa-eye" aria-hidden="true"></i>

                    </button>

                </div>

                <div id="strengthMessage" class="password-message" aria-live="polite"></div>

                <div class="input-field">

                    <span class="field-icon">

                        <i class="fa-solid fa-lock" aria-hidden="true"></i>

                    </span>

                    <input type="password" id="resetConfirmPassword" name="confirm_password"
                        placeholder="Confirm new password" minlength="8" autocomplete="new-password" required>

                    <button type="button" class="password-toggle" data-target="resetConfirmPassword"
                        aria-label="Show confirmed password" aria-pressed="false">

                        <i class="fa-solid fa-eye" aria-hidden="true"></i>

                    </button>

                </div>

                <div id="confirmMessage" class="password-message" aria-live="polite"></div>

                <button type="submit" class="submit-button">
                    Reset Password
                </button>

                <a href="Login.php" class="back-link">

                    <i class="bx bx-arrow-back" aria-hidden="true"></i>

                    Back to Login

                </a>

            </form>

        </section>

    </main>

    <script>
        document.addEventListener(
            "DOMContentLoaded",
            () => {
                const form =
                    document.getElementById(
                        "resetPasswordForm"
                    );

                const newPasswordInput =
                    document.getElementById(
                        "resetNewPassword"
                    );

                const confirmPasswordInput =
                    document.getElementById(
                        "resetConfirmPassword"
                    );

                const strengthMessage =
                    document.getElementById(
                        "strengthMessage"
                    );

                const confirmMessage =
                    document.getElementById(
                        "confirmMessage"
                    );

                if (
                    !form ||
                    !newPasswordInput ||
                    !confirmPasswordInput ||
                    !strengthMessage ||
                    !confirmMessage
                ) {
                    console.error(
                        "Reset password form elements are missing."
                    );

                    return;
                }

                function getPasswordStrength(
                    password
                ) {
                    if (password === "") {
                        return {
                            level: "",
                            text: ""
                        };
                    }

                    let score = 0;

                    if (password.length >= 8) {
                        score++;
                    }

                    if (password.length >= 12) {
                        score++;
                    }

                    if (/[a-z]/.test(password)) {
                        score++;
                    }

                    if (/[A-Z]/.test(password)) {
                        score++;
                    }

                    if (/[0-9]/.test(password)) {
                        score++;
                    }

                    if (
                        /[^A-Za-z0-9]/.test(
                            password
                        )
                    ) {
                        score++;
                    }

                    if (
                        password.length < 8 ||
                        score <= 3
                    ) {
                        return {
                            level: "weak",
                            text: "Password strength: Weak"
                        };
                    }

                    if (score <= 5) {
                        return {
                            level: "fair",
                            text: "Password strength: Fair"
                        };
                    }

                    return {
                        level: "strong",
                        text: "Password strength: Strong"
                    };
                }

                function updateStrength() {
                    const result =
                        getPasswordStrength(
                            newPasswordInput.value
                        );

                    strengthMessage.className =
                        "password-message";

                    if (result.level !== "") {
                        strengthMessage.classList.add(
                            result.level
                        );
                    }

                    strengthMessage.textContent =
                        result.text;
                }

                function updatePasswordMatch() {
                    confirmMessage.className =
                        "password-message";

                    if (
                        confirmPasswordInput.value ===
                        ""
                    ) {
                        confirmMessage.textContent =
                            "";

                        return false;
                    }

                    if (
                        newPasswordInput.value ===
                        confirmPasswordInput.value
                    ) {
                        confirmMessage.classList.add(
                            "match"
                        );

                        confirmMessage.textContent =
                            "Passwords match";

                        return true;
                    }

                    confirmMessage.classList.add(
                        "no-match"
                    );

                    confirmMessage.textContent =
                        "Passwords do not match";

                    return false;
                }

                function passwordMeetsRequirements(
                    password
                ) {
                    return (
                        password.length >= 8 &&
                        /[A-Z]/.test(password) &&
                        /[a-z]/.test(password) &&
                        /[0-9]/.test(password) &&
                        /[^A-Za-z0-9]/.test(
                            password
                        )
                    );
                }

                newPasswordInput.addEventListener(
                    "input",
                    () => {
                        updateStrength();
                        updatePasswordMatch();
                    }
                );

                confirmPasswordInput.addEventListener(
                    "input",
                    updatePasswordMatch
                );

                form.addEventListener(
                    "submit",
                    (event) => {
                        const password =
                            newPasswordInput.value;

                        const confirmation =
                            confirmPasswordInput.value;

                        if (
                            password !==
                            confirmation
                        ) {
                            event.preventDefault();

                            confirmMessage.className =
                                "password-message no-match";

                            confirmMessage.textContent =
                                "Passwords do not match";

                            confirmPasswordInput.focus();

                            return;
                        }

                        if (
                            !passwordMeetsRequirements(
                                password
                            )
                        ) {
                            event.preventDefault();

                            strengthMessage.className =
                                "password-message weak";

                            strengthMessage.textContent =
                                "Use at least 8 characters with uppercase, lowercase, number, and special character.";

                            newPasswordInput.focus();
                        }
                    }
                );
            }
        );
    </script>

    <script src="js/password-toggle.js"></script>

</body>

</html>
