const prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
).matches;
const menuButton = document.querySelector(".menu-button");
const navigation = document.querySelector("#site-navigation");

const closeNavigation = () => {
    menuButton?.setAttribute("aria-expanded", "false");
    navigation?.classList.remove("is-open");
};

menuButton?.addEventListener("click", () => {
    const isOpen = menuButton.getAttribute("aria-expanded") === "true";
    menuButton.setAttribute("aria-expanded", String(!isOpen));
    navigation?.classList.toggle("is-open", !isOpen);
});

navigation?.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", closeNavigation);
});

document.addEventListener("keydown", (event) => {
    const isNavigationOpen =
        menuButton?.getAttribute("aria-expanded") === "true";

    if (event.key === "Escape" && isNavigationOpen) {
        closeNavigation();
        menuButton?.focus();
    }
});

if (!prefersReducedMotion && "IntersectionObserver" in window) {
    const revealObserver = new IntersectionObserver(
        (entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15 },
    );

    document
        .querySelectorAll(".reveal")
        .forEach((element) => revealObserver.observe(element));
} else {
    document
        .querySelectorAll(".reveal")
        .forEach((element) => element.classList.add("is-visible"));
}

const sections = [...document.querySelectorAll("main section[id]")];
const navLinks = [...document.querySelectorAll("#site-navigation a")];

if (
    sections.length &&
    navLinks.length &&
    "IntersectionObserver" in window
) {
    const sectionObserver = new IntersectionObserver(
        (entries) => {
            const visible = entries.find((entry) => entry.isIntersecting);
            if (!visible) return;
            navLinks.forEach((link) => {
                const active =
                    link.getAttribute("href") === `#${visible.target.id}`;
                link.toggleAttribute("aria-current", active);
            });
        },
        { rootMargin: "-35% 0px -55% 0px", threshold: 0.01 },
    );

    sections.forEach((section) => sectionObserver.observe(section));
}
