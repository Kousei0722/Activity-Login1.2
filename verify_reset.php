<?php

session_start();

/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$error = "";

/*
|--------------------------------------------------------------------------
| CHECK RESET SESSION
|--------------------------------------------------------------------------
*/

$resetCode =
    $_SESSION["reset_code"]
    ?? null;

$resetExpires =
    (int) (
        $_SESSION["reset_expires"]
        ?? 0
    );

$resetUserId =
    (int) (
        $_SESSION["reset_user_id"]
        ?? 0
    );

/*
|--------------------------------------------------------------------------
| CHECK IF RESET REQUEST EXISTS
|--------------------------------------------------------------------------
*/

if (
    $resetCode === null
    || $resetUserId <= 0
) {
    $_SESSION["error"] =
        "No active password reset request was found.";

    header("Location: forgot_password.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK IF RESET CODE HAS EXPIRED
|--------------------------------------------------------------------------
*/

if (
    $resetExpires <= 0
    || time() > $resetExpires
) {
    unset(
        $_SESSION["reset_user_id"],
        $_SESSION["reset_email"],
        $_SESSION["reset_code"],
        $_SESSION["reset_expires"],
        $_SESSION["reset_verified"]
    );

    $_SESSION["error"] =
        "Reset code expired. Please generate a new code.";

    header("Location: forgot_password.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| PROCESS VERIFICATION FORM
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submittedCode =
        trim(
            $_POST["code"]
                ?? ""
        );

    /*
    |--------------------------------------------------------------------------
    | VALIDATE REQUIRED CODE
    |--------------------------------------------------------------------------
    */

    if ($submittedCode === "") {
        $error =
            "Please enter the reset code.";
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE CODE FORMAT
    |--------------------------------------------------------------------------
    */ elseif (
        !preg_match(
            "/^[0-9]{6}$/",
            $submittedCode
        )
    ) {
        $error =
            "Please enter a valid 6-digit code.";
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY RESET CODE
    |--------------------------------------------------------------------------
    */ elseif (
        hash_equals(
            (string) $resetCode,
            $submittedCode
        )
    ) {
        $_SESSION["reset_verified"] =
            true;

        /*
        Store the verification time for additional checking.
        */

        $_SESSION["reset_verified_at"] =
            time();

        header("Location: reset_password.php");
        exit;
    } else {
        $error =
            "Incorrect reset code.";
    }
}

/*
|--------------------------------------------------------------------------
| GET REMAINING CODE TIME
|--------------------------------------------------------------------------
*/

$remainingSeconds =
    max(
        0,
        $resetExpires - time()
    );

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verify Code</title>
    <link rel="stylesheet" href="css/verify_reset.css?v=1">

    <style>
        .form-message {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 15px;
            border-radius: 8px;
            background: rgba(155, 41, 52, 0.9);
            color: #ffffff;
            font-size: 0.9rem;
            line-height: 1.4;
            text-align: center;
        }

        .timer {
            margin: 12px 0;
            color: #ffffff;
            font-size: 0.85rem;
            text-align: center;
        }

        .timer strong {
            color: #00eeff;
        }

        .back {
            margin-top: 15px;
            text-align: center;
        }

        .back a {
            color: #ffffff;
            text-decoration: none;
        }

        .back a:hover {
            text-decoration: underline;
        }
    </style>

</head>

<body>

    <section>

        <div class="forgot-box">

            <form id="verifyCodeForm" action="verify_reset.php" method="POST" autocomplete="off">

                <h2>Verify Code</h2>

                <p class="text">
                    Enter the 6-digit reset code generated for your account.
                </p>

                <?php if ($error !== ""): ?>

                    <div class="form-message">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>

                <div class="input-box">

                    <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" minlength="6"
                        maxlength="6" autocomplete="one-time-code" required>

                    <label>
                        6-digit code
                    </label>

                </div>

                <p class="timer">
                    Code expires in
                    <strong id="countdown">
                        05:00
                    </strong>
                </p>

                <button type="submit" class="btn">
                    Verify
                </button>

                <div class="back">

                    <a href="forgot_password.php">
                        ← Generate a new code
                    </a>

                </div>

                <div class="back">

                    <a href="Login.php">
                        Back to Login
                    </a>

                </div>

            </form>

        </div>

    </section>

    <script>
        document.addEventListener(
            "DOMContentLoaded",
            function() {
                const codeInput =
                    document.getElementById(
                        "code"
                    );

                const countdownElement =
                    document.getElementById(
                        "countdown"
                    );

                let remainingSeconds =
                    <?= (int) $remainingSeconds ?>;

                /*
                |--------------------------------------------------------------------------
                | ACCEPT NUMBERS ONLY
                |--------------------------------------------------------------------------
                */

                codeInput.addEventListener(
                    "input",
                    function() {
                        this.value =
                            this.value
                            .replace(/\D/g, "")
                            .slice(0, 6);
                    }
                );

                /*
                |--------------------------------------------------------------------------
                | COUNTDOWN TIMER
                |--------------------------------------------------------------------------
                */

                function updateCountdown() {
                    const minutes =
                        Math.floor(
                            remainingSeconds / 60
                        );

                    const seconds =
                        remainingSeconds % 60;

                    countdownElement.textContent =
                        String(minutes).padStart(
                            2,
                            "0"
                        ) +
                        ":" +
                        String(seconds).padStart(
                            2,
                            "0"
                        );

                    if (remainingSeconds <= 0) {
                        clearInterval(timer);

                        countdownElement.textContent =
                            "Expired";

                        codeInput.disabled =
                            true;

                        return;
                    }

                    remainingSeconds--;
                }

                updateCountdown();

                const timer =
                    setInterval(
                        updateCountdown,
                        1000
                    );
            }
        );
    </script>

</body>

</html>