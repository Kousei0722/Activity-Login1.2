document.addEventListener("DOMContentLoaded", () => {

    document.querySelectorAll(".password-toggle").forEach((button) => {

        const input = document.getElementById(button.dataset.target);

        if (!input) return;

        // Hanapin ang lock icon sa parehong input container
        const container = input.closest(".input-box, .input-field");
        const lockIcon = container
            ? container.querySelector(".icon, .field-icon")
            : null;

        function updateIcons() {

            if (input.value.trim() === "") {

                // Walang input
                button.style.display = "none";

                if (lockIcon) {
                    lockIcon.style.display = "flex";
                }

            } else {

                // May input
                button.style.display = "flex";

                if (lockIcon) {
                    lockIcon.style.display = "none";
                }

            }

        }

        updateIcons();

        input.addEventListener("input", updateIcons);

        button.addEventListener("click", () => {

            const hidden = input.type === "password";

            input.type = hidden ? "text" : "password";

            const icon = button.querySelector("i");

            if (icon) {
                icon.classList.toggle("fa-eye", !hidden);
                icon.classList.toggle("fa-eye-slash", hidden);
            }

        });

    });

});