<?php

session_start();

require_once "config.php";
require_once "helpers.php";

$userId =
    (int) (
        $_SESSION["user_id"]
        ?? 0
    );

if ($userId > 0) {
    writeActivityLog(
        $conn,
        $userId,
        "Logged out"
    );
}

deleteCurrentRememberToken(
    $conn
);

$_SESSION = [];

if (
    ini_get(
        "session.use_cookies"
    )
) {
    $cookieParameters =
        session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires" => time() - 3600,
            "path" => $cookieParameters["path"],
            "domain" => $cookieParameters["domain"],
            "secure" => $cookieParameters["secure"],
            "httponly" => $cookieParameters["httponly"],
            "samesite" => "Lax"
        ]
    );
}

session_destroy();

$conn->close();

header(
    "Location: Login.php"
);

exit;
