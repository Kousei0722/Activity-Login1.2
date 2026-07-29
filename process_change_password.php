<?php

require_once "auth.php";
require_once "helpers.php";

/*
|--------------------------------------------------------------------------
| ALLOW POST REQUESTS ONLY
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"]
    !== "POST"
) {
    $conn->close();

    header(
        "Location: change_password.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| GET USER AND FORM DATA
|--------------------------------------------------------------------------
*/

$userId =
    (int) (
        $_SESSION["user_id"]
        ?? 0
    );

$currentPassword =
    $_POST["current_password"]
    ?? "";

$newPassword =
    $_POST["new_password"]
    ?? "";

$confirmPassword =
    $_POST["confirm_password"]
    ?? "";

/*
|--------------------------------------------------------------------------
| VALIDATE SESSION USER
|--------------------------------------------------------------------------
*/

if ($userId <= 0) {
    $_SESSION["password_error"] =
        "Your session has expired. Please log in again.";

    $conn->close();

    header(
        "Location: Login.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDATE REQUIRED FIELDS
|--------------------------------------------------------------------------
*/

if (
    $currentPassword === ""
    || $newPassword === ""
    || $confirmPassword === ""
) {
    $_SESSION["password_error"] =
        "Please complete all password fields.";

    $conn->close();

    header(
        "Location: change_password.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| GET CURRENT PASSWORD HASH
|--------------------------------------------------------------------------
*/

$userSql = "
    SELECT
        password,
        account_status
    FROM users
    WHERE id = ?
    LIMIT 1
";

$userStmt =
    $conn->prepare($userSql);

if (!$userStmt) {
    $_SESSION["password_error"] =
        "Unable to process your request.";

    $conn->close();

    header(
        "Location: change_password.php"
    );

    exit;
}

$userStmt->bind_param(
    "i",
    $userId
);

$userStmt->execute();

$userResult =
    $userStmt->get_result();

$user =
    $userResult->fetch_assoc();

$userStmt->close();

/*
|--------------------------------------------------------------------------
| CHECK IF USER EXISTS
|--------------------------------------------------------------------------
*/

if (!$user) {
    $_SESSION["password_error"] =
        "User account was not found.";

    $conn->close();

    header(
        "Location: change_password.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK ACCOUNT STATUS
|--------------------------------------------------------------------------
*/

if (
    strtolower(
        $user["account_status"]
            ?? "inactive"
    ) !== "active"
) {
    $_SESSION["password_error"] =
        "Your account is not active.";

    $conn->close();

    header(
        "Location: Login.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| VERIFY CURRENT PASSWORD
|--------------------------------------------------------------------------
*/

if (
    !password_verify(
        $currentPassword,
        $user["password"]
    )
) {
    writeActivityLog(
        $conn,
        $userId,
        "Failed password change: incorrect current password"
    );

    $_SESSION["password_error"] =
        "Current password is incorrect.";

    $conn->close();

    header(
        "Location: change_password.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDATE NEW PASSWORD LENGTH
|--------------------------------------------------------------------------
*/

if (
    strlen($newPassword) < 8
) {
    $_SESSION["password_error"] =
        "New password must be at least 8 characters long.";

    $conn->close();

    header(
        "Location: change_password.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDATE PASSWORD COMPLEXITY
|--------------------------------------------------------------------------
*/

$hasUppercase =
    preg_match(
        "/[A-Z]/",
        $newPassword
    );

$hasLowercase =
    preg_match(
        "/[a-z]/",
        $newPassword
    );

$hasNumber =
    preg_match(
        "/[0-9]/",
        $newPassword
    );

$hasSpecialCharacter =
    preg_match(
        "/[^A-Za-z0-9]/",
        $newPassword
    );

if (
    !$hasUppercase
    || !$hasLowercase
    || !$hasNumber
    || !$hasSpecialCharacter
) {
    $_SESSION["password_error"] =
        "New password must include an uppercase letter, lowercase letter, number, and special character.";

    $conn->close();

    header(
        "Location: change_password.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK PASSWORD CONFIRMATION
|--------------------------------------------------------------------------
*/

if (
    $newPassword
    !== $confirmPassword
) {
    $_SESSION["password_error"] =
        "Passwords do not match.";

    $conn->close();

    header(
        "Location: change_password.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| PREVENT REUSING CURRENT PASSWORD
|--------------------------------------------------------------------------
*/

if (
    password_verify(
        $newPassword,
        $user["password"]
    )
) {
    $_SESSION["password_error"] =
        "New password must be different from your current password.";

    $conn->close();

    header(
        "Location: change_password.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| HASH NEW PASSWORD
|--------------------------------------------------------------------------
*/

$newPasswordHash =
    password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );

if ($newPasswordHash === false) {
    $_SESSION["password_error"] =
        "Unable to secure the new password.";

    $conn->close();

    header(
        "Location: change_password.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| BEGIN DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | UPDATE PASSWORD
    |--------------------------------------------------------------------------
    */

    $updateSql = "
        UPDATE users
        SET
            password = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ";

    $updateStmt =
        $conn->prepare($updateSql);

    if (!$updateStmt) {
        throw new RuntimeException(
            "Unable to prepare password update."
        );
    }

    $updateStmt->bind_param(
        "si",
        $newPasswordHash,
        $userId
    );

    if (
        !$updateStmt->execute()
    ) {
        $updateStmt->close();

        throw new RuntimeException(
            "Unable to update password."
        );
    }

    $updateStmt->close();

    /*
    |--------------------------------------------------------------------------
    | REMOVE ALL REMEMBER ME TOKENS
    |--------------------------------------------------------------------------
    |
    | All browsers and devices previously remembered for this account
    | will be signed out.
    |--------------------------------------------------------------------------
    */

    $deleteTokensSql = "
        DELETE FROM remember_tokens
        WHERE user_id = ?
    ";

    $deleteTokensStmt =
        $conn->prepare(
            $deleteTokensSql
        );

    if (!$deleteTokensStmt) {
        throw new RuntimeException(
            "Unable to prepare token deletion."
        );
    }

    $deleteTokensStmt->bind_param(
        "i",
        $userId
    );

    if (
        !$deleteTokensStmt->execute()
    ) {
        $deleteTokensStmt->close();

        throw new RuntimeException(
            "Unable to delete remember tokens."
        );
    }

    $deleteTokensStmt->close();

    /*
    |--------------------------------------------------------------------------
    | SAVE ACTIVITY LOG
    |--------------------------------------------------------------------------
    */

    writeActivityLog(
        $conn,
        $userId,
        "Password changed successfully"
    );

    /*
    |--------------------------------------------------------------------------
    | COMMIT CHANGES
    |--------------------------------------------------------------------------
    */

    $conn->commit();
} catch (Throwable $error) {

    $conn->rollback();

    error_log(
        "Password change error: "
            . $error->getMessage()
    );

    $_SESSION["password_error"] =
        "Password update failed. Please try again.";

    $conn->close();

    header(
        "Location: change_password.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE REMEMBER ME COOKIE
|--------------------------------------------------------------------------
|
| The database tokens were removed above. This clears the corresponding
| cookie in the current browser.
|--------------------------------------------------------------------------
*/

deleteRememberCookie();

/*
|--------------------------------------------------------------------------
| REFRESH CURRENT SESSION SECURITY
|--------------------------------------------------------------------------
|
| The current session remains active, but receives a new session ID.
|--------------------------------------------------------------------------
*/

session_regenerate_id(
    true
);

$_SESSION["LAST_ACTIVITY"] =
    time();

$_SESSION["password_success"] =
    "Password changed successfully. For security, your saved Remember Me sessions were removed.";

/*
|--------------------------------------------------------------------------
| REMOVE OLD ERROR MESSAGE
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION["password_error"]
);

/*
|--------------------------------------------------------------------------
| CLOSE DATABASE AND REDIRECT
|--------------------------------------------------------------------------
*/

$conn->close();

header(
    "Location: change_password.php"
);

exit;
