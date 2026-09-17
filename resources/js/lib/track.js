/**
 * Front-end event reporting.
 *
 * The funnel could previously only see what the server happened to touch, so
 * "sessions" counted people who finished a quiz rather than people who arrived,
 * and quiz_start had never once been recorded — leaving no way to tell where in
 * the quiz anyone gave up.
 *
 * Fire-and-forget on purpose: analytics must never block a page or surface an
 * error to a visitor. Every failure is swallowed.
 */
export function track(event, data = {}, url = null) {
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        // keepalive so an event fired during navigation still leaves the page.
        fetch('/api/track', {
            method: 'POST',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify({
                event,
                url: url ?? window.location.pathname + window.location.search,
                data,
            }),
        }).catch(() => {});
    } catch {
        /* never let tracking break a page */
    }
}

/** Quiz progress, so abandonment is visible per step rather than as one cliff. */
export function trackQuizStep(step, total, extra = {}) {
    track('quiz_step', { step, total, ...extra });
}
