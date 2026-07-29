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
        "Location: profile.php"
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

$fullName =
    trim(
        $_POST["full_name"]
            ?? ""
    );

$username =
    trim(
        $_POST["username"]
            ?? ""
    );

$email =
    trim(
        $_POST["email"]
            ?? ""
    );

/*
|--------------------------------------------------------------------------
| VALIDATE LOGGED-IN USER
|--------------------------------------------------------------------------
*/

if ($userId <= 0) {
    $_SESSION["profile_error"] =
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
    $fullName === ""
    || $username === ""
    || $email === ""
) {
    $_SESSION["profile_error"] =
        "Please complete all profile fields.";

    $conn->close();

    header(
        "Location: profile.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDATE FULL NAME
|--------------------------------------------------------------------------
*/

if (
    strlen($fullName) < 2
) {
    $_SESSION["profile_error"] =
        "Full name must contain at least 2 characters.";

    $conn->close();

    header(
        "Location: profile.php"
    );

    exit;
}

if (
    strlen($fullName) > 100
) {
    $_SESSION["profile_error"] =
        "Full name must not exceed 100 characters.";

    $conn->close();

    header(
        "Location: profile.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDATE USERNAME
|--------------------------------------------------------------------------
*/

if (
    strlen($username) < 4
) {
    $_SESSION["profile_error"] =
        "Username must contain at least 4 characters.";

    $conn->close();

    header(
        "Location: profile.php"
    );

    exit;
}

if (
    strlen($username) > 50
) {
    $_SESSION["profile_error"] =
        "Username must not exceed 50 characters.";

    $conn->close();

    header(
        "Location: profile.php"
    );

    exit;
}

if (
    !preg_match(
        "/^[A-Za-z0-9_]+$/",
        $username
    )
) {
    $_SESSION["profile_error"] =
        "Username may only contain letters, numbers, and underscores.";

    $conn->close();

    header(
        "Location: profile.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDATE EMAIL
|--------------------------------------------------------------------------
*/

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {
    $_SESSION["profile_error"] =
        "Please enter a valid email address.";

    $conn->close();

    header(
        "Location: profile.php"
    );

    exit;
}

if (
    strlen($email) > 100
) {
    $_SESSION["profile_error"] =
        "Email address must not exceed 100 characters.";

    $conn->close();

    header(
        "Location: profile.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| GET CURRENT USER DETAILS
|--------------------------------------------------------------------------
|
| Ginagamit ito para malaman kung may actual changes bago mag-update.
|--------------------------------------------------------------------------
*/

$currentUserSql = "
    SELECT
        full_name,
        username,
        email,
        account_status
    FROM users
    WHERE id = ?
    LIMIT 1
";

$currentUserStmt =
    $conn->prepare(
        $currentUserSql
    );

if (!$currentUserStmt) {
    $_SESSION["profile_error"] =
        "Unable to load your current profile.";

    $conn->close();

    header(
        "Location: profile.php"
    );

    exit;
}

$currentUserStmt->bind_param(
    "i",
    $userId
);

$currentUserStmt->execute();

$currentUserResult =
    $currentUserStmt->get_result();

$currentUser =
    $currentUserResult->fetch_assoc();

$currentUserStmt->close();

/*
|--------------------------------------------------------------------------
| CHECK IF USER EXISTS
|--------------------------------------------------------------------------
*/

if (!$currentUser) {
    $_SESSION["profile_error"] =
        "Your user account could not be found.";

    $conn->close();

    header(
        "Location: Login.php"
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
        $currentUser["account_status"]
            ?? "inactive"
    ) !== "active"
) {
    $_SESSION["profile_error"] =
        "Your account is not active.";

    $conn->close();

    header(
        "Location: Login.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK DUPLICATE USERNAME OR EMAIL
|--------------------------------------------------------------------------
|
| The current user's own username and email are excluded.
|--------------------------------------------------------------------------
*/

$checkSql = "
    SELECT
        id,
        username,
        email
    FROM users
    WHERE (
        username = ?
        OR email = ?
    )
    AND id <> ?
    LIMIT 1
";

$checkStmt =
    $conn->prepare(
        $checkSql
    );

if (!$checkStmt) {
    $_SESSION["profile_error"] =
        "Unable to validate your profile information.";

    $conn->close();

    header(
        "Location: profile.php"
    );

    exit;
}

$checkStmt->bind_param(
    "ssi",
    $username,
    $email,
    $userId
);

$checkStmt->execute();

$checkResult =
    $checkStmt->get_result();

$existingUser =
    $checkResult->fetch_assoc();

$checkStmt->close();

if ($existingUser) {
    if (
        strtolower(
            $existingUser["username"]
        ) === strtolower($username)
    ) {
        $_SESSION["profile_error"] =
            "That username is already being used.";
    } else {
        $_SESSION["profile_error"] =
            "That email address is already being used.";
    }

    $conn->close();

    header(
        "Location: profile.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| CHECK FOR ACTUAL CHANGES
|--------------------------------------------------------------------------
*/

$hasChanges =
    $fullName !== $currentUser["full_name"]
    || $username !== $currentUser["username"]
    || $email !== $currentUser["email"];

if (!$hasChanges) {
    $_SESSION["profile_success"] =
        "No profile changes were detected.";

    $_SESSION["LAST_ACTIVITY"] =
        time();

    writeActivityLog(
        $conn,
        $userId,
        "Profile update submitted with no changes"
    );

    $conn->close();

    header(
        "Location: profile.php"
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
    | UPDATE USER PROFILE
    |--------------------------------------------------------------------------
    */

    $updateSql = "
        UPDATE users
        SET
            full_name = ?,
            username = ?,
            email = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ";

    $updateStmt =
        $conn->prepare(
            $updateSql
        );

    if (!$updateStmt) {
        throw new RuntimeException(
            "Unable to prepare profile update."
        );
    }

    $updateStmt->bind_param(
        "sssi",
        $fullName,
        $username,
        $email,
        $userId
    );

    if (
        !$updateStmt->execute()
    ) {
        $updateStmt->close();

        throw new RuntimeException(
            "Unable to update profile."
        );
    }

    $updateStmt->close();

    /*
    |--------------------------------------------------------------------------
    | SAVE ACTIVITY LOG
    |--------------------------------------------------------------------------
    */

    writeActivityLog(
        $conn,
        $userId,
        "Profile updated"
    );

    /*
    |--------------------------------------------------------------------------
    | COMMIT DATABASE CHANGES
    |--------------------------------------------------------------------------
    */

    $conn->commit();
} catch (Throwable $error) {

    $conn->rollback();

    error_log(
        "Profile update error: "
            . $error->getMessage()
    );

    $_SESSION["profile_error"] =
        "Profile update failed. Please try again.";

    $conn->close();

    header(
        "Location: profile.php"
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE SESSION VALUES
|--------------------------------------------------------------------------
*/

$_SESSION["full_name"] =
    $fullName;

$_SESSION["username"] =
    $username;

$_SESSION["email"] =
    $email;

$_SESSION["LAST_ACTIVITY"] =
    time();

/*
|--------------------------------------------------------------------------
| SET SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

$_SESSION["profile_success"] =
    "Profile updated successfully.";

unset(
    $_SESSION["profile_error"]
);

/*
|--------------------------------------------------------------------------
| REFRESH SESSION SECURITY
|--------------------------------------------------------------------------
*/

session_regenerate_id(
    true
);

/*
|--------------------------------------------------------------------------
| CLOSE DATABASE AND REDIRECT
|--------------------------------------------------------------------------
*/

$conn->close();

header(
    "Location: profile.php"
);

exit;
