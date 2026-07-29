<?php

require_once "auth.php";

/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$userId =
    (int) (
        $_SESSION["user_id"]
        ?? 0
    );

$userSql = "
    SELECT
        full_name,
        username,
        email,
        account_status
    FROM users
    WHERE id = ?
    LIMIT 1
";

$userStmt =
    $conn->prepare($userSql);

if (!$userStmt) {
    $conn->close();

    die("Unable to load user information.");
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
| USER NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$user) {
    $_SESSION = [];

    session_destroy();

    $conn->close();

    header("Location: Login.php");
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
    $_SESSION = [];

    session_destroy();

    $conn->close();

    header("Location: Login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| RECENT ACTIVITY
|--------------------------------------------------------------------------
*/

$logSql = "
    SELECT
        activity,
        ip_address,
        created_at
    FROM activity_logs
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 8
";

$logStmt =
    $conn->prepare($logSql);

if (!$logStmt) {
    $conn->close();

    die("Unable to load activity logs.");
}

$logStmt->bind_param(
    "i",
    $userId
);

$logStmt->execute();

$logs =
    $logStmt->get_result();

/*
|--------------------------------------------------------------------------
| DISPLAY VALUES
|--------------------------------------------------------------------------
*/

$accountStatus =
    ucfirst(
        $user["account_status"]
            ?? "active"
    );

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard</title>

    <link rel="stylesheet" href="css/Dashboard.css">

    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        /*
        |--------------------------------------------------------------------------
        | SESSION COUNTDOWN PANEL
        |--------------------------------------------------------------------------
        */

        .session-panel {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .session-status {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .session-dot {
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background: #32d583;
            box-shadow: 0 0 10px #32d583;
        }

        .session-dot.checking {
            background: #fdb022;
            box-shadow: 0 0 10px #fdb022;
        }

        .session-dot.error {
            background: #f04438;
            box-shadow: 0 0 10px #f04438;
        }

        .session-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .session-text strong {
            font-size: 0.95rem;
        }

        .session-text small {
            opacity: 0.8;
        }

        .session-countdown {
            min-width: 76px;
            padding: 8px 12px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.25);
            font-size: 1.2rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: 1px;
        }

        @media (max-width: 600px) {
            .session-panel {
                align-items: flex-start;
                flex-direction: column;
            }

            .session-countdown {
                width: 100%;
            }
        }
    </style>

</head>

<body>

    <!-- SIDEBAR -->

    <div class="sidebar">

        <h2>
            <?= htmlspecialchars($user["username"]) ?>
        </h2>

        <ul>

            <li>

                <a href="Dashboard.php">

                    <i class="bx bx-home"></i>

                    Dashboard

                </a>

            </li>

            <li>

                <a href="profile.php">

                    <i class="bx bx-user"></i>

                    Profile

                </a>

            </li>

            <li>

                <a href="change_password.php">

                    <i class="bx bx-lock-alt"></i>

                    Change Password

                </a>

            </li>

            <li>

                <a href="Logout.php">

                    <i class="bx bx-log-out"></i>

                    Logout

                </a>

            </li>

        </ul>

    </div>

    <!-- MAIN CONTENT -->

    <div class="main">

        <div class="top">

            <h1>
                Welcome,
                <?= htmlspecialchars($user["full_name"]) ?>!
            </h1>

        </div>

        <!-- SESSION COUNTDOWN -->

        <div class="session-panel">

            <div class="session-status">

                <span id="sessionDot" class="session-dot"></span>

                <div class="session-text">

                    <strong id="sessionStatus">
                        Session Active
                    </strong>

                    <small id="sessionDescription">
                        The session will be checked every 30 seconds.
                    </small>

                </div>

            </div>

            <div id="sessionCountdown" class="session-countdown">
                00:30
            </div>

        </div>

        <!-- USER CARDS -->

        <div class="cards">

            <div class="card">

                <h3>Username</h3>

                <p style="font-size: 22px;">
                    <?= htmlspecialchars($user["username"]) ?>
                </p>

            </div>

            <div class="card">

                <h3>Email</h3>

                <p style="font-size: 18px;">
                    <?= htmlspecialchars($user["email"]) ?>
                </p>

            </div>

            <div class="card">

                <h3>Status</h3>

                <p style="font-size: 22px;">
                    <?= htmlspecialchars($accountStatus) ?>
                </p>

            </div>

        </div>

        <!-- ACTIVITY TABLE -->

        <div class="table-box">

            <h2>Recent Activity</h2>

            <table>

                <thead>

                    <tr>

                        <th>Activity</th>

                        <th>IP Address</th>

                        <th>Date</th>

                    </tr>

                </thead>

                <tbody>

                    <?php if ($logs->num_rows > 0): ?>

                        <?php while ($row = $logs->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($row["activity"]) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["ip_address"]
                                            ?? "Unknown"
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row["created_at"]) ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="3">
                                No activity logs found.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <script src="js/Dashboard.js"></script>

</body>

</html>

<?php

$logStmt->close();
$conn->close();

?>