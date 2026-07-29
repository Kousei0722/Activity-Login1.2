<?php

declare(strict_types=1);

session_start();

require_once "config.php";

/*
|--------------------------------------------------------------------------
| REDIRECT LOGGED-IN USERS
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION["user_id"])
) {
    $conn->close();

    header("Location: Dashboard.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$error = "";
$success = "";
$resetCodeDisplay = "";
$oldEmail = "";

/*
|--------------------------------------------------------------------------
| CREATE CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION["forgot_csrf_token"])
) {
    $_SESSION["forgot_csrf_token"] =
        bin2hex(
            random_bytes(32)
        );
}

/*
|--------------------------------------------------------------------------
| GET SESSION MESSAGES
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION["forgot_error"])
) {
    $error =
        (string) $_SESSION["forgot_error"];

    unset(
        $_SESSION["forgot_error"]
    );
}

if (
    isset($_SESSION["forgot_success"])
) {
    $success =
        (string) $_SESSION["forgot_success"];

    unset(
        $_SESSION["forgot_success"]
    );
}

/*
|--------------------------------------------------------------------------
| PROCESS FORGOT PASSWORD FORM
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {
    $submittedCsrfToken =
        (string) (
            $_POST["csrf_token"]
            ?? ""
        );

    $sessionCsrfToken =
        (string) (
            $_SESSION["forgot_csrf_token"]
            ?? ""
        );

    $email =
        trim(
            (string) (
                $_POST["email"]
                ?? ""
            )
        );

    $oldEmail = $email;

    /*
    |--------------------------------------------------------------------------
    | VALIDATE CSRF TOKEN
    |--------------------------------------------------------------------------
    */

    if (
        $submittedCsrfToken === ""
        || $sessionCsrfToken === ""
        || !hash_equals(
            $sessionCsrfToken,
            $submittedCsrfToken
        )
    ) {
        $error =
            "Your session is invalid or expired. Please refresh the page.";
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE EMAIL
    |--------------------------------------------------------------------------
    */ elseif (
        $email === ""
    ) {
        $error =
            "Please enter your email address.";
    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        $error =
            "Please enter a valid email address.";
    }

    /*
    |--------------------------------------------------------------------------
    | FIND ACCOUNT
    |--------------------------------------------------------------------------
    */ else {
        $findUserSql = "
            SELECT
                id,
                full_name,
                email,
                account_status
            FROM users
            WHERE email = ?
            LIMIT 1
        ";

        $findUserStmt =
            $conn->prepare(
                $findUserSql
            );

        if (
            !$findUserStmt
        ) {
            $error =
                "Unable to process your request. Please try again.";
        } else {
            $findUserStmt->bind_param(
                "s",
                $email
            );

            if (
                !$findUserStmt->execute()
            ) {
                $error =
                    "Unable to process your request. Please try again.";
            } else {
                $result =
                    $findUserStmt->get_result();

                $user =
                    $result->fetch_assoc();

                /*
                |--------------------------------------------------------------------------
                | ACCOUNT NOT FOUND
                |--------------------------------------------------------------------------
                */

                if (
                    !$user
                ) {
                    $error =
                        "No account was found using that email address.";
                }

                /*
                |--------------------------------------------------------------------------
                | CHECK ACCOUNT STATUS
                |--------------------------------------------------------------------------
                */ elseif (
                    isset($user["account_status"])
                    && strtolower(
                        (string) $user["account_status"]
                    ) !== "active"
                ) {
                    $error =
                        "This account is currently inactive.";
                }

                /*
                |--------------------------------------------------------------------------
                | CREATE RESET CODE
                |--------------------------------------------------------------------------
                */ else {
                    $resetCode =
                        (string) random_int(
                            100000,
                            999999
                        );

                    $resetExpires =
                        time() + 900;

                    /*
                    |--------------------------------------------------------------------------
                    | STORE RESET DETAILS IN SESSION
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION["reset_user_id"] =
                        (int) $user["id"];

                    $_SESSION["reset_email"] =
                        (string) $user["email"];

                    $_SESSION["reset_code"] =
                        $resetCode;

                    $_SESSION["reset_expires"] =
                        $resetExpires;

                    $_SESSION["reset_verified"] =
                        false;

                    /*
                    |--------------------------------------------------------------------------
                    | ROTATE CSRF TOKEN
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION["forgot_csrf_token"] =
                        bin2hex(
                            random_bytes(32)
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | SIMULATOR DISPLAY
                    |--------------------------------------------------------------------------
                    |
                    | Development simulator lamang ito.
                    | Sa real website, dapat ipadala sa email ang code.
                    |--------------------------------------------------------------------------
                    */

                    $resetCodeDisplay =
                        $resetCode;

                    $success =
                        "Reset code generated successfully. The code expires in 15 minutes.";
                }
            }

            $findUserStmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| CLOSE DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Forgot Password</title>

    <!-- FONT AWESOME -->

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- BOXICONS -->

    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">

    <!-- FORGOT PASSWORD CSS -->

    <link rel="stylesheet" href="css/forgotpass.css?v=5">

</head>

<body>

    <main class="page-wrapper">

        <div class="forgot-card">

            <div class="card-icon">

                <i class="fa-solid fa-lock" aria-hidden="true"></i>

            </div>

            <h1>
                Forgot Password
            </h1>

            <p class="description">

                Enter your email address. This simulator will display your password reset code directly.

            </p>

            <?php if (
                $error !== ""
            ): ?>

                <div class="message error-message" role="alert">

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

            <?php if (
                $success !== ""
            ): ?>

                <div class="message success-message" role="status">

                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>

                    <span>

                        <?= htmlspecialchars(
                            $success,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </span>

                </div>

            <?php endif; ?>

            <?php if (
                $resetCodeDisplay !== ""
            ): ?>

                <div class="reset-code-box">

                    <span class="reset-code-label">

                        Your reset code

                    </span>

                    <strong id="generatedResetCode" class="reset-code">

                        <?= htmlspecialchars(
                            $resetCodeDisplay,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </strong>

                    <button type="button" id="copyCodeButton" class="copy-code-button">

                        <i class="fa-regular fa-copy" aria-hidden="true"></i>

                        Copy Code

                    </button>

                </div>

                <a href="verify_reset.php" class="continue-button">

                    Verify Reset Code

                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>

                </a>

            <?php else: ?>

                <form action="forgot_password.php" method="POST" autocomplete="off" class="forgot-form">

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                                                                        (string) $_SESSION["forgot_csrf_token"],
                                                                        ENT_QUOTES,
                                                                        "UTF-8"
                                                                    ) ?>">

                    <div class="input-field">

                        <span class="input-icon">

                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>

                        </span>

                        <input type="email" name="email" id="email" value="<?= htmlspecialchars(
                                                                                $oldEmail,
                                                                                ENT_QUOTES,
                                                                                "UTF-8"
                                                                            ) ?>" placeholder="Email address" maxlength="100" autocomplete="email"
                            autocapitalize="none" spellcheck="false" required>

                    </div>

                    <button type="submit" class="submit-button">

                        Generate Code

                    </button>

                </form>

            <?php endif; ?>

            <a href="Login.php" class="back-link">

                <i class="bx bx-arrow-back" aria-hidden="true"></i>

                Back to Login

            </a>

        </div>

    </main>

    <script>
        document.addEventListener(
            "DOMContentLoaded",
            () => {
                const copyButton =
                    document.getElementById(
                        "copyCodeButton"
                    );

                const codeElement =
                    document.getElementById(
                        "generatedResetCode"
                    );

                if (
                    !copyButton ||
                    !codeElement
                ) {
                    return;
                }

                copyButton.addEventListener(
                    "click",
                    async () => {
                        const resetCode =
                            codeElement.textContent.trim();

                        try {
                            await navigator.clipboard.writeText(
                                resetCode
                            );

                            copyButton.innerHTML = `
                                <i
                                    class="fa-solid fa-check"
                                    aria-hidden="true"
                                ></i>

                                Copied
                            `;

                            window.setTimeout(
                                () => {
                                    copyButton.innerHTML = `
                                        <i
                                            class="fa-regular fa-copy"
                                            aria-hidden="true"
                                        ></i>

                                        Copy Code
                                    `;
                                },
                                2000
                            );
                        } catch (
                            error
                        ) {
                            console.error(
                                "Unable to copy reset code:",
                                error
                            );
                        }
                    }
                );
            }
        );
    </script>

</body>

</html>