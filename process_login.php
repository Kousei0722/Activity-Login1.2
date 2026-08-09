<?php

session_start();

require_once "config.php";
require_once "helpers.php";

if (
    $_SERVER["REQUEST_METHOD"]
    !== "POST"
) {
    $conn->close();

    header(
        "Location: Login.php"
    );

    exit;
}

$identifier =
    trim(
        $_POST["login_identifier"]
            ?? ""
    );

$password =
    $_POST["login_password"]
    ?? "";

$rememberMe =
    isset(
        $_POST["remember_me"]
    )
    && $_POST["remember_me"] === "1";

if (
    $identifier === ""
    || $password === ""
) {
    $_SESSION["error"] =
        "Please enter your username or email and password.";

    $_SESSION["old_login_identifier"] =
        $identifier;

    $conn->close();

    header(
        "Location: Login.php"
    );

    exit;
}

$sql = "
    SELECT
        id,
        full_name,
        username,
        email,
        password,
        role,
        account_status,
        failed_attempts,
        lock_until
    FROM users
    WHERE username = ?
        OR email = ?
    LIMIT 1
";

$stmt =
    $conn->prepare($sql);

if (!$stmt) {
    $_SESSION["error"] =
        "Unable to process your login request.";

    $conn->close();

    header(
        "Location: Login.php"
    );

    exit;
}

$stmt->bind_param(
    "ss",
    $identifier,
    $identifier
);

$stmt->execute();

$result =
    $stmt->get_result();

$user =
    $result->fetch_assoc();

$stmt->close();

if (!$user) {
    $_SESSION["error"] =
        "Invalid username, email, or password.";

    $_SESSION["old_login_identifier"] =
        $identifier;

    $conn->close();

    header(
        "Location: Login.php"
    );

    exit;
}

$userId =
    (int) $user["id"];

$failedAttempts =
    (int) (
        $user["failed_attempts"]
        ?? 0
    );

$lockUntilValue =
    $user["lock_until"]
    ?? null;

$lockUntilTimestamp =
    $lockUntilValue
    ? strtotime($lockUntilValue)
    : 0;

if (
    $lockUntilTimestamp > time()
) {
    $_SESSION["error"] =
        "Your account is temporarily locked. Please wait for the countdown.";

    $_SESSION["old_login_identifier"] =
        $identifier;

    $_SESSION["lock_until_timestamp"] =
        $lockUntilTimestamp;

    $conn->close();

    header(
        "Location: Login.php"
    );

    exit;
}

if (
    $lockUntilTimestamp > 0
    && $lockUntilTimestamp <= time()
) {
    $clearLockSql = "
        UPDATE users
        SET
            failed_attempts = 0,
            lock_until = NULL
        WHERE id = ?
    ";

    $clearLockStmt =
        $conn->prepare(
            $clearLockSql
        );

    if ($clearLockStmt) {
        $clearLockStmt->bind_param(
            "i",
            $userId
        );

        $clearLockStmt->execute();
        $clearLockStmt->close();
    }

    $failedAttempts = 0;
}

if (
    strtolower(
        $user["account_status"]
            ?? "inactive"
    ) !== "active"
) {
    $_SESSION["error"] =
        "Your account is not active.";

    $_SESSION["old_login_identifier"] =
        $identifier;

    $conn->close();

    header(
        "Location: Login.php"
    );

    exit;
}

if (
    !password_verify(
        $password,
        $user["password"]
    )
) {
    $failedAttempts++;

    if ($failedAttempts >= 5) {
        $lockUntilTimestamp =
            time() + (15 * 60);

        $lockUntilDatabase =
            date(
                "Y-m-d H:i:s",
                $lockUntilTimestamp
            );

        $lockSql = "
            UPDATE users
            SET
                failed_attempts = ?,
                lock_until = ?
            WHERE id = ?
        ";

        $lockStmt =
            $conn->prepare(
                $lockSql
            );

        if ($lockStmt) {
            $lockStmt->bind_param(
                "isi",
                $failedAttempts,
                $lockUntilDatabase,
                $userId
            );

            $lockStmt->execute();
            $lockStmt->close();
        }

        $_SESSION["error"] =
            "Too many failed login attempts. Your account is locked for 15 minutes.";

        $_SESSION["lock_until_timestamp"] =
            $lockUntilTimestamp;
    } else {
        $attemptSql = "
            UPDATE users
            SET failed_attempts = ?
            WHERE id = ?
        ";

        $attemptStmt =
            $conn->prepare(
                $attemptSql
            );

        if ($attemptStmt) {
            $attemptStmt->bind_param(
                "ii",
                $failedAttempts,
                $userId
            );

            $attemptStmt->execute();
            $attemptStmt->close();
        }

        $remainingAttempts =
            5 - $failedAttempts;

        $_SESSION["error"] =
            "Invalid username, email, or password. "
            . $remainingAttempts
            . " attempt(s) remaining.";
    }

    $_SESSION["old_login_identifier"] =
        $identifier;

    writeActivityLog(
        $conn,
        $userId,
        "Failed login attempt"
    );

    $conn->close();

    header(
        "Location: Login.php"
    );

    exit;
}

$resetSql = "
    UPDATE users
    SET
        failed_attempts = 0,
        lock_until = NULL
    WHERE id = ?
";

$resetStmt =
    $conn->prepare(
        $resetSql
    );

if ($resetStmt) {
    $resetStmt->bind_param(
        "i",
        $userId
    );

    $resetStmt->execute();
    $resetStmt->close();
}

session_regenerate_id(
    true
);

$_SESSION["user_id"] =
    $userId;

$_SESSION["full_name"] =
    $user["full_name"];

$_SESSION["username"] =
    $user["username"];

$_SESSION["email"] =
    $user["email"];

$_SESSION["role"] =
    $user["role"]
    ?? "user";

$_SESSION["LAST_ACTIVITY"] =
    time();

if ($rememberMe) {
    $rememberCreated =
        createRememberToken(
            $conn,
            $userId
        );

    if (!$rememberCreated) {

        error_log(
            "Remember Me token was not created for user ID: "
                . $userId
        );
    }
} else {

    deleteCurrentRememberToken(
        $conn
    );
}

unset(
    $_SESSION["error"],
    $_SESSION["success"],
    $_SESSION["old_login_identifier"],
    $_SESSION["lock_until_timestamp"]
);

writeActivityLog(
    $conn,
    $userId,
    $rememberMe
        ? "Logged in with Remember Me"
        : "Logged in"
);

$conn->close();

header(
    "Location: Dashboard.php"
);

exit;
