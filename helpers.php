<?php

/*
|--------------------------------------------------------------------------
| REMEMBER COOKIE NAME
|--------------------------------------------------------------------------
*/

const REMEMBER_COOKIE_NAME = "remember_login";

/*
|--------------------------------------------------------------------------
| REMEMBER COOKIE DURATION
|--------------------------------------------------------------------------
|
| 30 days
|--------------------------------------------------------------------------
*/

const REMEMBER_COOKIE_DURATION = 60 * 60 * 24 * 30;

/*
|--------------------------------------------------------------------------
| CHECK HTTPS
|--------------------------------------------------------------------------
*/

function isHttpsConnection(): bool
{
    return (
        !empty($_SERVER["HTTPS"])
        && $_SERVER["HTTPS"] !== "off"
    );
}

/*
|--------------------------------------------------------------------------
| SET REMEMBER COOKIE
|--------------------------------------------------------------------------
*/

function setRememberCookie(
    string $selector,
    string $validator,
    int $expiresAt
): void {
    $cookieValue =
        $selector . ":" . $validator;

    setcookie(
        REMEMBER_COOKIE_NAME,
        $cookieValue,
        [
            "expires" => $expiresAt,
            "path" => "/",
            "domain" => "",
            "secure" => isHttpsConnection(),
            "httponly" => true,
            "samesite" => "Lax"
        ]
    );

    /*
    Make the cookie available during the current request.
    */

    $_COOKIE[REMEMBER_COOKIE_NAME] =
        $cookieValue;
}

/*
|--------------------------------------------------------------------------
| DELETE REMEMBER COOKIE
|--------------------------------------------------------------------------
*/

function deleteRememberCookie(): void
{
    setcookie(
        REMEMBER_COOKIE_NAME,
        "",
        [
            "expires" => time() - 3600,
            "path" => "/",
            "domain" => "",
            "secure" => isHttpsConnection(),
            "httponly" => true,
            "samesite" => "Lax"
        ]
    );

    unset(
        $_COOKIE[REMEMBER_COOKIE_NAME]
    );
}

/*
|--------------------------------------------------------------------------
| PARSE REMEMBER COOKIE
|--------------------------------------------------------------------------
*/

function parseRememberCookie(): ?array
{
    $cookie =
        $_COOKIE[REMEMBER_COOKIE_NAME]
        ?? "";

    if ($cookie === "") {
        return null;
    }

    $parts =
        explode(
            ":",
            $cookie,
            2
        );

    if (count($parts) !== 2) {
        return null;
    }

    [$selector, $validator] =
        $parts;

    if (
        !preg_match(
            "/^[a-f0-9]{24}$/",
            $selector
        )
    ) {
        return null;
    }

    if (
        !preg_match(
            "/^[a-f0-9]{64}$/",
            $validator
        )
    ) {
        return null;
    }

    return [
        "selector" => $selector,
        "validator" => $validator
    ];
}

/*
|--------------------------------------------------------------------------
| DELETE EXPIRED TOKENS
|--------------------------------------------------------------------------
*/

function deleteExpiredRememberTokens(
    mysqli $conn
): void {
    $sql = "
        DELETE FROM remember_tokens
        WHERE expires_at <= NOW()
    ";

    $conn->query($sql);
}

/*
|--------------------------------------------------------------------------
| DELETE USER REMEMBER TOKENS
|--------------------------------------------------------------------------
*/

function deleteUserRememberTokens(
    mysqli $conn,
    int $userId
): void {
    $sql = "
        DELETE FROM remember_tokens
        WHERE user_id = ?
    ";

    $stmt =
        $conn->prepare($sql);

    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        "i",
        $userId
    );

    $stmt->execute();
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| DELETE CURRENT REMEMBER TOKEN
|--------------------------------------------------------------------------
*/

function deleteCurrentRememberToken(
    mysqli $conn
): void {
    $rememberCookie =
        parseRememberCookie();

    if (!$rememberCookie) {
        deleteRememberCookie();
        return;
    }

    $selector =
        $rememberCookie["selector"];

    $sql = "
        DELETE FROM remember_tokens
        WHERE selector = ?
    ";

    $stmt =
        $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param(
            "s",
            $selector
        );

        $stmt->execute();
        $stmt->close();
    }

    deleteRememberCookie();
}

/*
|--------------------------------------------------------------------------
| CREATE REMEMBER TOKEN
|--------------------------------------------------------------------------
*/

function createRememberToken(
    mysqli $conn,
    int $userId
): bool {
    /*
    Remove old tokens for the same account.
    */

    deleteUserRememberTokens(
        $conn,
        $userId
    );

    deleteExpiredRememberTokens(
        $conn
    );

    try {
        $selector =
            bin2hex(
                random_bytes(12)
            );

        $validator =
            bin2hex(
                random_bytes(32)
            );
    } catch (Throwable $error) {
        error_log(
            "Remember token generation failed: "
                . $error->getMessage()
        );

        return false;
    }

    $tokenHash =
        hash(
            "sha256",
            $validator
        );

    $expiresTimestamp =
        time()
        + REMEMBER_COOKIE_DURATION;

    $expiresAt =
        date(
            "Y-m-d H:i:s",
            $expiresTimestamp
        );

    $sql = "
        INSERT INTO remember_tokens
        (
            user_id,
            selector,
            token_hash,
            expires_at
        )
        VALUES (?, ?, ?, ?)
    ";

    $stmt =
        $conn->prepare($sql);

    if (!$stmt) {
        error_log(
            "Remember token prepare failed: "
                . $conn->error
        );

        return false;
    }

    $stmt->bind_param(
        "isss",
        $userId,
        $selector,
        $tokenHash,
        $expiresAt
    );

    $success =
        $stmt->execute();

    if (!$success) {
        error_log(
            "Remember token insert failed: "
                . $stmt->error
        );

        $stmt->close();

        return false;
    }

    $stmt->close();

    setRememberCookie(
        $selector,
        $validator,
        $expiresTimestamp
    );

    return true;
}

/*
|--------------------------------------------------------------------------
| LOGIN USER FROM REMEMBER COOKIE
|--------------------------------------------------------------------------
*/

function tryRememberLogin(
    mysqli $conn
): bool {
    if (
        isset($_SESSION["user_id"])
        && (int) $_SESSION["user_id"] > 0
    ) {
        return true;
    }

    $rememberCookie =
        parseRememberCookie();

    if (!$rememberCookie) {
        return false;
    }

    deleteExpiredRememberTokens(
        $conn
    );

    $selector =
        $rememberCookie["selector"];

    $validator =
        $rememberCookie["validator"];

    $sql = "
        SELECT
            rt.id AS token_id,
            rt.user_id,
            rt.token_hash,
            rt.expires_at,
            u.full_name,
            u.username,
            u.email,
            u.role,
            u.account_status
        FROM remember_tokens AS rt
        INNER JOIN users AS u
            ON u.id = rt.user_id
        WHERE rt.selector = ?
        LIMIT 1
    ";

    $stmt =
        $conn->prepare($sql);

    if (!$stmt) {
        deleteRememberCookie();
        return false;
    }

    $stmt->bind_param(
        "s",
        $selector
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    $record =
        $result->fetch_assoc();

    $stmt->close();

    if (!$record) {
        deleteRememberCookie();
        return false;
    }

    if (
        strtotime(
            $record["expires_at"]
        ) <= time()
    ) {
        deleteCurrentRememberToken(
            $conn
        );

        return false;
    }

    if (
        strtolower(
            $record["account_status"]
                ?? "inactive"
        ) !== "active"
    ) {
        deleteCurrentRememberToken(
            $conn
        );

        return false;
    }

    $incomingHash =
        hash(
            "sha256",
            $validator
        );

    if (
        !hash_equals(
            $record["token_hash"],
            $incomingHash
        )
    ) {
        deleteCurrentRememberToken(
            $conn
        );

        return false;
    }

    /*
    Protect against session fixation.
    */

    session_regenerate_id(
        true
    );

    $_SESSION["user_id"] =
        (int) $record["user_id"];

    $_SESSION["full_name"] =
        $record["full_name"];

    $_SESSION["username"] =
        $record["username"];

    $_SESSION["email"] =
        $record["email"];

    $_SESSION["role"] =
        $record["role"]
        ?? "user";

    $_SESSION["LAST_ACTIVITY"] =
        time();

    /*
    Rotate token after successful automatic login.
    */

    createRememberToken(
        $conn,
        (int) $record["user_id"]
    );

    return true;
}

/*
|--------------------------------------------------------------------------
| GET CLIENT IP ADDRESS
|--------------------------------------------------------------------------
*/

function getClientIpAddress(): string
{
    return (
        $_SERVER["REMOTE_ADDR"]
        ?? "Unknown"
    );
}

/*
|--------------------------------------------------------------------------
| WRITE ACTIVITY LOG
|--------------------------------------------------------------------------
*/

function writeActivityLog(
    mysqli $conn,
    int $userId,
    string $activity
): void {
    $ipAddress =
        getClientIpAddress();

    $sql = "
        INSERT INTO activity_logs
        (
            user_id,
            activity,
            ip_address
        )
        VALUES (?, ?, ?)
    ";

    $stmt =
        $conn->prepare($sql);

    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        "iss",
        $userId,
        $activity,
        $ipAddress
    );

    $stmt->execute();
    $stmt->close();
}
