document.addEventListener(
    "DOMContentLoaded",
    () => {

        const loginBox =
            document.querySelector(
                ".login-box"
            );

        const signUpBox =
            document.querySelector(
                ".sign-up"
            );

        const registerLink =
            document.querySelector(
                ".login-box .register-link a"
            );

        const loginLink =
            document.querySelector(
                ".sign-up .register-link a"
            );

        const loginForm =
            document.getElementById(
                "loginForm"
            );

        const loginEmailInput =
            document.getElementById(
                "loginEmail"
            );

        const loginPasswordInput =
            document.getElementById(
                "loginPassword"
            );

        const rememberMeInput =
            document.getElementById(
                "rememberMe"
            );

        const registerForm =
            document.getElementById(
                "registerForm"
            );

        const fullNameInput =
            document.getElementById(
                "regFullName"
            );

        const usernameInput =
            document.getElementById(
                "regUsername"
            );

        const emailInput =
            document.getElementById(
                "regEmail"
            );

        const passwordInput =
            document.getElementById(
                "regPassword"
            );

        const confirmPasswordInput =
            document.getElementById(
                "confirmPassword"
            );

        const strengthMessage =
            document.getElementById(
                "strengthMessage"
            );

        const confirmMessage =
            document.getElementById(
                "confirmMessage"
            );

        const rememberStatusKey =
            "loginRememberEnabled";

        const savedIdentifierKey =
            "savedLoginIdentifier";

        function restoreRememberedLogin() {
            const rememberEnabled =
                localStorage.getItem(
                    rememberStatusKey
                ) === "true";

            const savedIdentifier =
                localStorage.getItem(
                    savedIdentifierKey
                ) || "";

            if (
                rememberEnabled
                && savedIdentifier !== ""
            ) {
                if (loginEmailInput) {
                    loginEmailInput.value =
                        savedIdentifier;
                }

                if (rememberMeInput) {
                    rememberMeInput.checked =
                        true;
                }
            } else {
                if (loginEmailInput) {
                    loginEmailInput.value =
                        "";
                }

                if (rememberMeInput) {
                    rememberMeInput.checked =
                        false;
                }

                localStorage.removeItem(
                    rememberStatusKey
                );

                localStorage.removeItem(
                    savedIdentifierKey
                );
            }

            if (loginPasswordInput) {
                loginPasswordInput.value =
                    "";
            }
        }

        restoreRememberedLogin();

        window.setTimeout(
            restoreRememberedLogin,
            100
        );

        window.setTimeout(
            restoreRememberedLogin,
            500
        );

        loginForm?.addEventListener(
            "submit",
            () => {
                const identifier =
                    loginEmailInput
                        ?.value
                        .trim()
                    || "";

                const rememberChecked =
                    rememberMeInput
                        ?.checked
                    || false;

                if (
                    rememberChecked
                    && identifier !== ""
                ) {
                    localStorage.setItem(
                        rememberStatusKey,
                        "true"
                    );

                    localStorage.setItem(
                        savedIdentifierKey,
                        identifier
                    );
                } else {
                    localStorage.removeItem(
                        rememberStatusKey
                    );

                    localStorage.removeItem(
                        savedIdentifierKey
                    );
                }
            }
        );

        rememberMeInput?.addEventListener(
            "change",
            () => {
                if (
                    !rememberMeInput.checked
                ) {
                    localStorage.removeItem(
                        rememberStatusKey
                    );

                    localStorage.removeItem(
                        savedIdentifierKey
                    );
                }
            }
        );

        if (signUpBox) {
            signUpBox.style.display =
                "none";
        }

        function clearRegisterFields() {
            const registerInputs = [
                fullNameInput,
                usernameInput,
                emailInput,
                passwordInput,
                confirmPasswordInput
            ];

            registerInputs.forEach(
                (input) => {
                    if (!input) {
                        return;
                    }

                    input.value = "";

                    input.removeAttribute(
                        "value"
                    );
                }
            );

            if (strengthMessage) {
                strengthMessage.textContent =
                    "";

                strengthMessage.style.color =
                    "";
            }

            if (confirmMessage) {
                confirmMessage.textContent =
                    "";

                confirmMessage.style.color =
                    "";
            }
        }

        clearRegisterFields();

        window.setTimeout(
            clearRegisterFields,
            100
        );

        window.setTimeout(
            clearRegisterFields,
            500
        );

        registerLink?.addEventListener(
            "click",
            (event) => {
                event.preventDefault();

                if (loginBox) {
                    loginBox.style.display =
                        "none";
                }

                if (signUpBox) {
                    signUpBox.style.display =
                        "flex";
                }

                clearRegisterFields();

                window.setTimeout(
                    clearRegisterFields,
                    50
                );

                fullNameInput?.focus();
            }
        );

        loginLink?.addEventListener(
            "click",
            (event) => {
                event.preventDefault();

                if (signUpBox) {
                    signUpBox.style.display =
                        "none";
                }

                if (loginBox) {
                    loginBox.style.display =
                        "flex";
                }

                registerForm?.reset();

                clearRegisterFields();

                restoreRememberedLogin();

                loginEmailInput?.focus();
            }
        );

        function getPasswordStrength(
            password
        ) {
            if (password.length === 0) {
                return {
                    label: "",
                    color: ""
                };
            }

            let score = 0;

            if (password.length >= 8) {
                score++;
            }

            if (password.length >= 12) {
                score++;
            }

            if (/[a-z]/.test(password)) {
                score++;
            }

            if (/[A-Z]/.test(password)) {
                score++;
            }

            if (/[0-9]/.test(password)) {
                score++;
            }

            if (
                /[^A-Za-z0-9]/.test(
                    password
                )
            ) {
                score++;
            }

            if (
                password.length < 8
                || score <= 3
            ) {
                return {
                    label: "Weak",
                    color: "#ff5b67"
                };
            }

            if (score <= 5) {
                return {
                    label: "Fair",
                    color: "#ffd15b"
                };
            }

            return {
                label: "Strong",
                color: "#44ff9a"
            };
        }

        function updatePasswordStrength() {
            if (
                !passwordInput
                || !strengthMessage
            ) {
                return;
            }

            const result =
                getPasswordStrength(
                    passwordInput.value
                );

            if (result.label === "") {
                strengthMessage.textContent =
                    "";

                strengthMessage.style.color =
                    "";

                return;
            }

            strengthMessage.textContent =
                "Password strength: "
                + result.label;

            strengthMessage.style.color =
                result.color;
        }

        passwordInput?.addEventListener(
            "input",
            updatePasswordStrength
        );
        
        function updatePasswordMatch() {
            if (
                !passwordInput
                || !confirmPasswordInput
                || !confirmMessage
            ) {
                return;
            }

            if (
                confirmPasswordInput.value
                === ""
            ) {
                confirmMessage.textContent =
                    "";

                confirmMessage.style.color =
                    "";

                return;
            }

            if (
                passwordInput.value
                === confirmPasswordInput.value
            ) {
                confirmMessage.textContent =
                    "Passwords match";

                confirmMessage.style.color =
                    "#44ff9a";
            } else {
                confirmMessage.textContent =
                    "Passwords do not match";

                confirmMessage.style.color =
                    "#ff5b67";
            }
        }

        passwordInput?.addEventListener(
            "input",
            updatePasswordMatch
        );

        confirmPasswordInput
            ?.addEventListener(
                "input",
                updatePasswordMatch
            );

        registerForm?.addEventListener(
            "submit",
            (event) => {
                const password =
                    passwordInput?.value
                    ?? "";

                const confirmation =
                    confirmPasswordInput?.value
                    ?? "";

                if (
                    password
                    !== confirmation
                ) {
                    event.preventDefault();

                    if (confirmMessage) {
                        confirmMessage.textContent =
                            "Passwords do not match";

                        confirmMessage.style.color =
                            "#ff5b67";
                    }

                    confirmPasswordInput?.focus();
                }
            }
        );

        const lockUntil =
            Number(
                window.LOCK_UNTIL
                || 0
            );

        const countdownOutput =
            document.getElementById(
                "lockCountdown"
            );

        const loginButton =
            document.getElementById(
                "loginSubmit"
            );

        let countdownInterval =
            null;

        function formatCountdown(
            seconds
        ) {
            const safeSeconds =
                Math.max(
                    0,
                    Math.ceil(seconds)
                );

            const minutes =
                Math.floor(
                    safeSeconds / 60
                );

            const remainingSeconds =
                safeSeconds % 60;

            return (
                String(minutes).padStart(
                    2,
                    "0"
                )
                + ":"
                + String(
                    remainingSeconds
                ).padStart(
                    2,
                    "0"
                )
            );
        }

        function updateLockCountdown() {
            if (
                lockUntil <= 0
                || !countdownOutput
            ) {
                return;
            }

            const currentTime =
                Date.now() / 1000;

            const remainingTime =
                lockUntil
                - currentTime;

            countdownOutput.textContent =
                formatCountdown(
                    remainingTime
                );

            if (remainingTime <= 0) {
                if (countdownInterval) {
                    window.clearInterval(
                        countdownInterval
                    );
                }

                if (loginButton) {
                    loginButton.disabled =
                        false;

                    loginButton.textContent =
                        "Login";
                }

                window.location.reload();
            }
        }

        if (
            lockUntil > 0
            && countdownOutput
        ) {
            updateLockCountdown();

            countdownInterval =
                window.setInterval(
                    updateLockCountdown,
                    1000
                );
        }
    }
);
