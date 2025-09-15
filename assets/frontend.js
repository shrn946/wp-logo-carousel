(function ($) {
    document.addEventListener('DOMContentLoaded', function () {
        const carousels = document.querySelectorAll('.glc-carousel');
        if (!carousels.length) return;

        carousels.forEach(initCarousel);

        function initCarousel(el) {
            let raw = el.getAttribute('data-settings');
            if (!raw) return;
            let settings = JSON.parse(raw);

            const track = el.querySelector('.glc-track');
            if (!track) return;

            // helpers
            const gap = Number(settings.gap) || 0;
            const speed = Number(settings.speed) || 30; // px/sec
            const direction = settings.direction === 'right' ? 1 : -1;

            function itemFullWidth(item) {
                const cs = getComputedStyle(item);
                const mr = parseFloat(cs.marginRight) || 0;
                return item.offsetWidth + mr;
            }

            function calcWidth(nodes) {
                return nodes.reduce((sum, n) => sum + itemFullWidth(n), 0);
            }

            function removeClones() {
                Array.from(track.querySelectorAll('.glc-clone')).forEach(n => n.remove());
                // remove any leftover original flags (safe)
                Array.from(track.querySelectorAll('.glc-item')).forEach(i => i.classList.remove('glc-original'));
            }

            function buildClones() {
                // mark originals (the items rendered by PHP)
                const originalItems = Array.from(track.querySelectorAll('.glc-item')).filter(i => !i.classList.contains('glc-clone'));
                if (!originalItems.length) return { originalWidth: 0, containerWidth: el.clientWidth };

                originalItems.forEach(i => i.classList.add('glc-original'));

                const containerWidth = el.clientWidth;
                const originalWidth = calcWidth(originalItems);

                // Ensure track has at least (containerWidth + originalWidth) worth of content by appending clones
                let trackWidth = calcWidth(Array.from(track.querySelectorAll('.glc-item')));
                let cycles = 0;
                while (trackWidth < containerWidth + originalWidth && cycles < 10) {
                    originalItems.forEach(it => {
                        const c = it.cloneNode(true);
                        c.classList.add('glc-clone');
                        track.appendChild(c);
                        trackWidth += itemFullWidth(it);
                    });
                    cycles++;
                }

                // Also prepend at least one copy (and more if needed) so right-to-left / left-to-right both have content
                cycles = 0;
                while (trackWidth < containerWidth * 2 + originalWidth && cycles < 10) {
                    for (let i = originalItems.length - 1; i >= 0; i--) {
                        const it = originalItems[i];
                        const c = it.cloneNode(true);
                        c.classList.add('glc-clone');
                        track.insertBefore(c, track.firstChild);
                        trackWidth += itemFullWidth(it);
                    }
                    cycles++;
                }

                // final widths
                return { originalWidth, containerWidth };
            }

            // Wait for images to load so width calculations are accurate
            const imgs = Array.from(track.querySelectorAll('img'));
            const imgsLoaded = Promise.all(imgs.map(img => {
                if (img.complete) return Promise.resolve();
                return new Promise(res => { img.addEventListener('load', res); img.addEventListener('error', res); });
            }));

            let tween = null;

            function startAnimation(originalShift) {
                if (tween) tween.kill();
                // choose animation direction & start position so loop is seamless
                if (direction === -1) { // move left
                    gsap.set(track, { x: 0 });
                    tween = gsap.to(track, {
                        x: -originalShift,
                        ease: 'none',
                        duration: Math.max(0.1, originalShift / speed),
                        repeat: -1
                    });
                } else { // move right
                    gsap.set(track, { x: -originalShift });
                    tween = gsap.to(track, {
                        x: 0,
                        ease: 'none',
                        duration: Math.max(0.1, originalShift / speed),
                        repeat: -1
                    });
                }

                // attach to element for pause/resume access
                el._glc_tween = tween;
            }

            // debounce helper
            function debounce(fn, wait = 150) {
                let t;
                return function () {
                    clearTimeout(t);
                    t = setTimeout(() => fn.apply(this, arguments), wait);
                };
            }

            // initialize after images loaded
            imgsLoaded.then(() => {
                removeClones();
                const sizes = buildClones();
                if (!sizes.originalWidth) return;

                // performance hint
                track.style.willChange = 'transform';

                if (settings.autoplay) startAnimation(sizes.originalWidth);

                // hover pause should affect only this carousel tween
                if (settings.pause_on_hover) {
                    el.addEventListener('mouseenter', () => { if (el._glc_tween) el._glc_tween.pause(); });
                    el.addEventListener('mouseleave', () => { if (el._glc_tween) el._glc_tween.resume(); });
                }

                // handle resize: rebuild clones & restart animation
                window.addEventListener('resize', debounce(() => {
                    if (el._glc_tween) el._glc_tween.kill();
                    removeClones();
                    const s = buildClones();
                    if (!s.originalWidth) return;
                    if (settings.autoplay) startAnimation(s.originalWidth);
                }, 200));
            });
        }
    });
})(jQuery);
