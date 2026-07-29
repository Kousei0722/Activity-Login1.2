<?php

require_once "auth.php";

/*
|--------------------------------------------------------------------------
| GET LOGGED-IN USER ID
|--------------------------------------------------------------------------
*/

$userId =
    (int) (
        $_SESSION["user_id"]
        ?? 0
    );

/*
|--------------------------------------------------------------------------
| GET ONE-TIME SESSION MESSAGES
|--------------------------------------------------------------------------
*/

$error =
    $_SESSION["profile_error"]
    ?? "";

$success =
    $_SESSION["profile_success"]
    ?? "";

unset(
    $_SESSION["profile_error"],
    $_SESSION["profile_success"]
);

/*
|--------------------------------------------------------------------------
| GET USER PROFILE
|--------------------------------------------------------------------------
*/

$userSql = "
    SELECT
        full_name,
        username,
        email,
        role,
        account_status,
        profile_picture,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
";

$userStmt =
    $conn->prepare($userSql);

if (!$userStmt) {
    $conn->close();

    $_SESSION["error"] =
        "Unable to load your profile.";

    header("Location: Dashboard.php");
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
$conn->close();

/*
|--------------------------------------------------------------------------
| CHECK IF USER EXISTS
|--------------------------------------------------------------------------
*/

if (!$user) {
    $_SESSION = [];

    session_destroy();

    header("Location: Login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| PROFILE VALUES
|--------------------------------------------------------------------------
*/

$displayRole =
    !empty($user["role"])
    ? ucfirst($user["role"])
    : "Registered User";

$displayStatus =
    !empty($user["account_status"])
    ? ucfirst($user["account_status"])
    : "Active";

$memberSince =
    !empty($user["created_at"])
    ? date(
        "F j, Y",
        strtotime($user["created_at"])
    )
    : "Not available";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile</title>

    <link rel="stylesheet" href="css/profile.css">

    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        .profile-card {
            width: min(460px, 92vw);
        }

        .profile-image {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .profile-image i {
            font-size: 100px;
        }

        .profile-card h2 {
            margin-bottom: 4px;
            text-align: center;
        }

        .role {
            margin-bottom: 18px;
            text-align: center;
        }

        .message {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 12px;
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .message.success {
            background: #176f4b;
        }

        .message.error {
            background: #9b2934;
        }

        .info {
            margin-bottom: 18px;
        }

        .info .row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
        }

        .info .row span {
            font-weight: 600;
        }

        .info .row p {
            margin: 0;
            text-align: right;
            word-break: break-word;
        }

        .edit-form {
            width: 100%;
            margin-top: 10px;
        }

        .edit-form .form-group {
            margin-bottom: 12px;
        }

        .edit-form label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .edit-form input {
            width: 100%;
            padding: 11px 12px;
            border: 0;
            border-radius: 8px;
            outline: none;
        }

        .edit-form button {
            width: 100%;
            margin-top: 5px;
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .buttons a {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            padding: 11px;
            border: 0;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
        }

        .logout-button {
            background: #9b2934;
            color: #ffffff;
        }

        .dashboard-button {
            background: #176f4b;
            color: #ffffff;
        }

        @media (max-width: 480px) {
            .info .row {
                flex-direction: column;
                gap: 3px;
            }

            .info .row p {
                text-align: left;
            }

            .buttons {
                flex-direction: column;
            }
        }
    </style>

</head>

<body>

    <div class="container">

        <div class="profile-card">

            <!-- PROFILE ICON -->

            <div class="profile-image">

                <i class="bx bxs-user-circle"></i>

            </div>

            <!-- USERNAME AND ROLE -->

            <h2>
                <?= htmlspecialchars($user["username"]) ?>
            </h2>

            <p class="role">
                <?= htmlspecialchars($displayRole) ?>
            </p>

            <!-- ERROR MESSAGE -->

            <?php if ($error !== ""): ?>

                <div class="message error">

                    <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>

            <!-- SUCCESS MESSAGE -->

            <?php if ($success !== ""): ?>

                <div class="message success">

                    <?= htmlspecialchars($success) ?>

                </div>

            <?php endif; ?>

            <!-- PROFILE INFORMATION -->

            <div class="info">

                <div class="row">

                    <span>Full Name</span>

                    <p>
                        <?= htmlspecialchars($user["full_name"]) ?>
                    </p>

                </div>

                <div class="row">

                    <span>Email</span>

                    <p>
                        <?= htmlspecialchars($user["email"]) ?>
                    </p>

                </div>

                <div class="row">

                    <span>Status</span>

                    <p>
                        <?= htmlspecialchars($displayStatus) ?>
                    </p>

                </div>

                <div class="row">

                    <span>Member Since</span>

                    <p>
                        <?= htmlspecialchars($memberSince) ?>
                    </p>

                </div>

            </div>

            <!-- EDIT PROFILE FORM -->

            <form class="edit-form" action="update_profile.php" method="POST" autocomplete="off">

                <div class="form-group">

                    <label for="fullName">
                        Full Name
                    </label>

                    <input type="text" id="fullName" name="full_name"
                        value="<?= htmlspecialchars($user["full_name"]) ?>" minlength="2" maxlength="100"
                        autocomplete="name" required>

                </div>

                <div class="form-group">

                    <label for="username">
                        Username
                    </label>

                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($user["username"]) ?>"
                        minlength="4" maxlength="50" pattern="[A-Za-z0-9_]+"
                        title="Use letters, numbers, and underscores only." autocomplete="username" required>

                </div>

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user["email"]) ?>"
                        maxlength="100" autocomplete="email" required>

                </div>

                <button type="submit" id="editBtn">
                    <i class="bx bx-save"></i>
                    Save Profile
                </button>

            </form>

            <!-- NAVIGATION BUTTONS -->

            <div class="buttons">

                <a href="Dashboard.php" class="dashboard-button">
                    <i class="bx bx-home"></i>
                    Dashboard
                </a>

                <a href="Logout.php" class="logout-button">
                    <i class="bx bx-log-out"></i>
                    Logout
                </a>

            </div>

        </div>

    </div>

</body>

</html>