document.addEventListener(
    "DOMContentLoaded",
    () => {

        const currentPage =
            window.location.pathname
                .split("/")
                .pop()
                .toLowerCase();

        document
            .querySelectorAll(".sidebar a")
            .forEach((link) => {

                const href =
                    link
                        .getAttribute("href")
                        ?.toLowerCase();

                if (href === currentPage) {
                    link.classList.add(
                        "active"
                    );
                }

            });

        const logoutLink =
            document.querySelector(
                'a[href="Logout.php"]'
            );

        if (logoutLink) {

            logoutLink.addEventListener(
                "click",
                (event) => {

                    const confirmed =
                        window.confirm(
                            "Are you sure you want to logout?"
                        );

                    if (!confirmed) {
                        event.preventDefault();
                    }

                }
            );

        }

        const welcome =
            document.querySelector(
                ".top h1"
            );

        if (welcome) {

            welcome.style.opacity =
                "0";

            welcome.style.transform =
                "translateY(15px)";

            window.setTimeout(
                () => {

                    welcome.style.transition =
                        "all 0.5s ease";

                    welcome.style.opacity =
                        "1";

                    welcome.style.transform =
                        "translateY(0)";

                },
                100
            );

        }

        document
            .querySelectorAll(".card")
            .forEach((card) => {

                card.addEventListener(
                    "mouseenter",
                    () => {

                        card.style.transform =
                            "translateY(-6px)";

                    }
                );

                card.addEventListener(
                    "mouseleave",
                    () => {

                        card.style.transform =
                            "translateY(0)";

                    }
                );

            });

        const sidebar =
            document.querySelector(
                ".sidebar"
            );

        function updateSidebar() {

            if (!sidebar) {
                return;
            }

            if (
                window.innerWidth <= 768
            ) {

                sidebar.classList.add(
                    "collapsed"
                );

            } else {

                sidebar.classList.remove(
                    "collapsed"
                );

            }

        }

        updateSidebar();

        window.addEventListener(
            "resize",
            updateSidebar
        );

        const logoutDuration =
            30;

        let remainingSeconds =
            logoutDuration;

        let countdownInterval =
            null;

        let logoutStarted =
            false;

        const countdownElement =
            document.getElementById(
                "sessionCountdown"
            );

        const statusElement =
            document.getElementById(
                "sessionStatus"
            );

        const descriptionElement =
            document.getElementById(
                "sessionDescription"
            );

        const statusDot =
            document.getElementById(
                "sessionDot"
            );

        function formatTime(seconds) {

            const safeSeconds =
                Math.max(
                    0,
                    seconds
                );

            const minutes =
                Math.floor(
                    safeSeconds / 60
                );

            const secondsLeft =
                safeSeconds % 60;

            return (
                String(minutes).padStart(
                    2,
                    "0"
                )
                + ":"
                + String(secondsLeft).padStart(
                    2,
                    "0"
                )
            );

        }

        function updateCountdownDisplay() {

            if (countdownElement) {

                countdownElement.textContent =
                    formatTime(
                        remainingSeconds
                    );

            }

        }

        function updateStatus(
            title,
            description,
            statusType = "active"
        ) {

            if (statusElement) {
                statusElement.textContent =
                    title;
            }

            if (descriptionElement) {
                descriptionElement.textContent =
                    description;
            }

            if (statusDot) {

                statusDot.className =
                    "session-dot";

                if (
                    statusType === "warning"
                ) {
                    statusDot.classList.add(
                        "checking"
                    );
                }

                if (
                    statusType === "expired"
                ) {
                    statusDot.classList.add(
                        "error"
                    );
                }

            }

        }

        function automaticLogout() {

            if (logoutStarted) {
                return;
            }

            logoutStarted =
                true;

            if (countdownInterval) {

                window.clearInterval(
                    countdownInterval
                );

            }

            remainingSeconds =
                0;

            updateCountdownDisplay();

            updateStatus(
                "Session Expired",
                "You were logged out because of inactivity.",
                "expired"
            );

            window.setTimeout(
                () => {

                    window.location.replace(
                        "Logout.php?reason=inactive"
                    );

                },
                1000
            );

        }

        function resetInactivityTimer() {

            if (logoutStarted) {
                return;
            }

            remainingSeconds =
                logoutDuration;

            updateCountdownDisplay();

            updateStatus(
                "Session Active",
                "Automatic logout after 30 seconds of inactivity.",
                "active"
            );

        }

        function startCountdown() {

            updateCountdownDisplay();

            countdownInterval =
                window.setInterval(
                    () => {

                        if (logoutStarted) {
                            return;
                        }

                        remainingSeconds--;

                        updateCountdownDisplay();

                        if (
                            remainingSeconds <= 10
                            && remainingSeconds > 0
                        ) {

                            updateStatus(
                                "Session Expiring",
                                "Move your mouse or press a key to remain logged in.",
                                "warning"
                            );

                        }

                        if (
                            remainingSeconds <= 0
                        ) {

                            automaticLogout();

                        }

                    },
                    1000
                );

        }

        const activityEvents = [
            "mousedown",
            "mousemove",
            "keydown",
            "scroll",
            "touchstart",
            "click"
        ];

        let activityResetDelay =
            null;

        function handleUserActivity() {

            if (activityResetDelay) {
                return;
            }

            resetInactivityTimer();

            activityResetDelay =
                window.setTimeout(
                    () => {

                        activityResetDelay =
                            null;

                    },
                    500
                );

        }

        activityEvents.forEach(
            (eventName) => {

                document.addEventListener(
                    eventName,
                    handleUserActivity,
                    {
                        passive: true
                    }
                );

            }
        );

        let hiddenStartedAt =
            null;

        document.addEventListener(
            "visibilitychange",
            () => {

                if (document.hidden) {

                    hiddenStartedAt =
                        Date.now();

                    return;

                }

                if (hiddenStartedAt === null) {
                    return;
                }

                const hiddenSeconds =
                    Math.floor(
                        (
                            Date.now()
                            - hiddenStartedAt
                        ) / 1000
                    );

                hiddenStartedAt =
                    null;

                remainingSeconds -=
                    hiddenSeconds;

                if (
                    remainingSeconds <= 0
                ) {

                    automaticLogout();

                } else {

                    updateCountdownDisplay();

                }

            }
        );

        resetInactivityTimer();
        startCountdown();

    }
);
