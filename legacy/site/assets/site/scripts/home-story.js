(function () {
    "use strict";

    var root = document.getElementById("home-story");
    var hero = document.querySelector("[data-home-hero]");
    if (!root || !hero) {
        return;
    }

    var items = Array.prototype.slice.call(root.querySelectorAll("[data-home-story-feature]"));
    var indexNode = document.getElementById("home-story-index");
    var labelNode = document.getElementById("home-story-label");
    var progressNode = document.getElementById("home-story-progress");
    var preview = document.getElementById("home-story-preview");
    var previewKicker = document.getElementById("home-story-preview-kicker");
    var previewTitle = document.getElementById("home-story-preview-title");
    var previewCaption = document.getElementById("home-story-preview-caption");
    var stageLink = document.getElementById("home-story-stage-link");
    var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var activeItem = null;
    var scrollFrame = 0;
    var previewTimer = 0;

    function persianNumber(value, minimumDigits) {
        return Number(value).toLocaleString("fa-IR", {
            minimumIntegerDigits: minimumDigits || 1,
            useGrouping: false
        });
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function updatePreview(item) {
        var position = items.indexOf(item);
        if (position < 0) {
            return;
        }

        items.forEach(function (candidate) {
            candidate.classList.toggle("is-active", candidate === item);
        });

        var current = position + 1;
        if (indexNode) {
            indexNode.textContent = persianNumber(current, 2) + " / " + persianNumber(items.length, 2);
        }
        if (labelNode) {
            labelNode.textContent = item.dataset.storyLabel || "امکانات سایت";
        }
        if (progressNode) {
            progressNode.style.transform = "scaleX(" + (current / Math.max(1, items.length)).toFixed(4) + ")";
        }
        if (stageLink) {
            stageLink.href = item.dataset.storyHref || "/app/";
        }

        if (!preview) {
            return;
        }

        window.clearTimeout(previewTimer);
        preview.classList.add("is-changing");
        previewTimer = window.setTimeout(function () {
            preview.dataset.mode = item.dataset.storyMode || "default";
            if (previewKicker) {
                previewKicker.textContent = item.dataset.storyKicker || "";
            }
            if (previewTitle) {
                previewTitle.textContent = item.dataset.storyTitle || "";
            }
            if (previewCaption) {
                previewCaption.textContent = item.dataset.storyCaption || "";
            }
            preview.classList.remove("is-changing");
        }, reduceMotion ? 0 : 140);
    }

    function activate(item) {
        if (!item || item === activeItem) {
            return;
        }
        activeItem = item;
        updatePreview(item);
    }

    function animateCount(node) {
        var target = Number(node.dataset.homeCount || 0);
        if (!Number.isFinite(target) || target < 0) {
            return;
        }
        if (reduceMotion) {
            node.textContent = persianNumber(target);
            return;
        }

        var startedAt = performance.now();
        var duration = 860;
        function tick(now) {
            var elapsed = clamp((now - startedAt) / duration, 0, 1);
            var eased = 1 - Math.pow(1 - elapsed, 3);
            node.textContent = persianNumber(Math.round(target * eased));
            if (elapsed < 1) {
                window.requestAnimationFrame(tick);
            }
        }
        window.requestAnimationFrame(tick);
    }

    function syncScrollMotion() {
        scrollFrame = 0;
        if (reduceMotion || window.innerWidth <= 760) {
            document.documentElement.style.setProperty("--home-scroll", "0");
            return;
        }
        var rect = hero.getBoundingClientRect();
        var travel = Math.max(1, Math.min(hero.offsetHeight * .82, window.innerHeight));
        var progress = clamp(-rect.top / travel, 0, 1);
        document.documentElement.style.setProperty("--home-scroll", progress.toFixed(4));
    }

    function requestScrollMotion() {
        if (scrollFrame) {
            return;
        }
        scrollFrame = window.requestAnimationFrame(syncScrollMotion);
    }

    function setupRevealMotion() {
        var revealNodes = Array.prototype.slice.call(document.querySelectorAll([
            ".home-active-exams",
            ".home-section-heading",
            ".home-service-group",
            ".home-story__intro",
            ".home-promo-card",
            "#home-identity-panel"
        ].join(",")));

        revealNodes.forEach(function (node) {
            node.setAttribute("data-home-reveal", "");
        });

        if (reduceMotion || !("IntersectionObserver" in window)) {
            revealNodes.forEach(function (node) {
                node.classList.add("is-revealed");
            });
            return;
        }

        var revealObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                entry.target.classList.add("is-revealed");
                observer.unobserve(entry.target);
            });
        }, {
            rootMargin: "0px 0px -8% 0px",
            threshold: .08
        });

        revealNodes.forEach(function (node) {
            revealObserver.observe(node);
        });
    }

    if ("IntersectionObserver" in window) {
        var countObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                animateCount(entry.target);
                observer.unobserve(entry.target);
            });
        }, { threshold: .58 });

        root.querySelectorAll("[data-home-count]").forEach(function (node) {
            countObserver.observe(node);
        });

        var featureObserver = new IntersectionObserver(function (entries) {
            var visible = entries
                .filter(function (entry) { return entry.isIntersecting; })
                .sort(function (a, b) { return b.intersectionRatio - a.intersectionRatio; });
            if (visible[0]) {
                activate(visible[0].target);
            }
        }, {
            rootMargin: window.innerWidth <= 760 ? "-31% 0px -49% 0px" : "-25% 0px -44% 0px",
            threshold: [.05, .22, .48, .7]
        });

        items.forEach(function (item) {
            featureObserver.observe(item);
            item.addEventListener("pointerenter", function () {
                if (window.matchMedia("(hover: hover)").matches) {
                    activate(item);
                }
            });
            item.addEventListener("focusin", function () {
                activate(item);
            });
        });
    } else {
        root.querySelectorAll("[data-home-count]").forEach(animateCount);
    }

    setupRevealMotion();
    activate(items[0]);
    syncScrollMotion();
    window.addEventListener("scroll", requestScrollMotion, { passive: true });
    window.addEventListener("resize", requestScrollMotion, { passive: true });
})();
