<?php

session_start();

require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: Login.php");
    exit;
}

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

$password =
    $_POST["password"]
    ?? "";

$confirmPassword =
    $_POST["confirm_password"]
    ?? "";

$_SESSION["old_register"] = [
    "full_name" => $fullName,
    "username" => $username,
    "email" => $email
];

if (
    $fullName === ""
    || $username === ""
    || $email === ""
    || $password === ""
    || $confirmPassword === ""
) {
    $_SESSION["error"] =
        "Please complete all registration fields.";

    header("Location: Login.php");
    exit;
}

if (strlen($fullName) < 2) {
    $_SESSION["error"] =
        "Full name must contain at least 2 characters.";

    header("Location: Login.php");
    exit;
}

if (strlen($fullName) > 100) {
    $_SESSION["error"] =
        "Full name must not exceed 100 characters.";

    header("Location: Login.php");
    exit;
}

if (strlen($username) < 4) {
    $_SESSION["error"] =
        "Username must contain at least 4 characters.";

    header("Location: Login.php");
    exit;
}

if (strlen($username) > 50) {
    $_SESSION["error"] =
        "Username must not exceed 50 characters.";

    header("Location: Login.php");
    exit;
}

if (
    !preg_match(
        "/^[A-Za-z0-9_]+$/",
        $username
    )
) {
    $_SESSION["error"] =
        "Username may only contain letters, numbers, and underscores.";

    header("Location: Login.php");
    exit;
}

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {
    $_SESSION["error"] =
        "Please enter a valid email address.";

    header("Location: Login.php");
    exit;
}

if (strlen($email) > 100) {
    $_SESSION["error"] =
        "Email address must not exceed 100 characters.";

    header("Location: Login.php");
    exit;
}

if (strlen($password) < 8) {
    $_SESSION["error"] =
        "Password must be at least 8 characters long.";

    header("Location: Login.php");
    exit;
}

$hasUppercase =
    preg_match(
        "/[A-Z]/",
        $password
    );

$hasLowercase =
    preg_match(
        "/[a-z]/",
        $password
    );

$hasNumber =
    preg_match(
        "/[0-9]/",
        $password
    );

$hasSpecialCharacter =
    preg_match(
        "/[^A-Za-z0-9]/",
        $password
    );

if (
    !$hasUppercase
    || !$hasLowercase
    || !$hasNumber
    || !$hasSpecialCharacter
) {
    $_SESSION["error"] =
        "Password must include uppercase, lowercase, number, and special character.";

    header("Location: Login.php");
    exit;
}

if ($password !== $confirmPassword) {
    $_SESSION["error"] =
        "Passwords do not match.";

    header("Location: Login.php");
    exit;
}

$checkUserSql = "
    SELECT id
    FROM users
    WHERE username = ?
       OR email = ?
    LIMIT 1
";

$checkUserStmt =
    $conn->prepare($checkUserSql);

if (!$checkUserStmt) {
    $_SESSION["error"] =
        "Unable to process your registration request.";

    $conn->close();

    header("Location: Login.php");
    exit;
}

$checkUserStmt->bind_param(
    "ss",
    $username,
    $email
);

$checkUserStmt->execute();

$checkUserResult =
    $checkUserStmt->get_result();

$existingUser =
    $checkUserResult->fetch_assoc();

$checkUserStmt->close();

if ($existingUser) {
    $_SESSION["error"] =
        "Username or email already exists.";

    $conn->close();

    header("Location: Login.php");
    exit;
}

$passwordHash =
    password_hash(
        $password,
        PASSWORD_DEFAULT
    );

if ($passwordHash === false) {
    $_SESSION["error"] =
        "Unable to secure your password.";

    $conn->close();

    header("Location: Login.php");
    exit;
}

$insertUserSql = "
    INSERT INTO users (
        full_name,
        username,
        email,
        password,
        role,
        account_status
    )
    VALUES (
        ?,
        ?,
        ?,
        ?,
        'user',
        'active'
    )
";

$insertUserStmt =
    $conn->prepare($insertUserSql);

if (!$insertUserStmt) {
    $_SESSION["error"] =
        "Unable to create your account.";

    $conn->close();

    header("Location: Login.php");
    exit;
}

$insertUserStmt->bind_param(
    "ssss",
    $fullName,
    $username,
    $email,
    $passwordHash
);

$registrationSuccessful =
    $insertUserStmt->execute();

$insertUserStmt->close();

if ($registrationSuccessful) {
    unset(
        $_SESSION["old_register"]
    );

    $_SESSION["success"] =
        "Registration successful. You may now log in.";
} else {
    $_SESSION["error"] =
        "Registration failed. Please try again.";
}

$conn->close();

header("Location: Login.php");
exit;
