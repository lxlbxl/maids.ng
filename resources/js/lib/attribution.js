/**
 * First-touch traffic attribution, captured on the landing page and carried
 * into the WhatsApp handoff.
 *
 * On first visit we snapshot utm_* / gclid / fbclid / referrer / landing path
 * into the `mng_ft` cookie (90 days, first-touch — never overwritten). Every
 * WhatsApp CTA then appends those values to /wa/{source} so the server can
 * record a wa_clicks row and reconstruct the source in the conversation.
 */

const COOKIE = 'mng_ft';
const MAX_AGE = 60 * 60 * 24 * 90; // 90 days

function readCookie(name) {
    try {
        const m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
        return m ? decodeURIComponent(m.pop()) : null;
    } catch {
        return null;
    }
}

function writeCookie(name, value) {
    try {
        document.cookie =
            `${name}=${encodeURIComponent(value)}; Max-Age=${MAX_AGE}; Path=/; SameSite=Lax`;
    } catch {
        /* ignore */
    }
}

function paramsFromUrl() {
    const q = new URLSearchParams(window.location.search);
    const pick = (k) => (q.get(k) || '').slice(0, 200) || undefined;
    return {
        utm_source: pick('utm_source'),
        utm_medium: pick('utm_medium'),
        utm_campaign: pick('utm_campaign'),
        utm_content: pick('utm_content'),
        utm_term: pick('utm_term'),
        gclid: pick('gclid'),
        fbclid: pick('fbclid'),
    };
}

/** Capture first-touch once. Safe to call on every page load. */
export function captureFirstTouch() {
    if (readCookie(COOKIE)) return;
    const p = paramsFromUrl();
    const ft = {
        ...p,
        ref: (document.referrer || '').slice(0, 300) || undefined,
        lp: (window.location.pathname || '/').slice(0, 200),
        ts: new Date().toISOString(),
    };
    // Only set the cookie if there's something worth remembering, OR always set a
    // "direct" marker so we don't keep re-checking the URL on later navigations.
    writeCookie(COOKIE, JSON.stringify(ft));
}

export function getFirstTouch() {
    const raw = readCookie(COOKIE);
    if (!raw) return {};
    try {
        return JSON.parse(raw) || {};
    } catch {
        return {};
    }
}

function posthogDistinctId() {
    try {
        return window.posthog?.get_distinct_id?.() || undefined;
    } catch {
        return undefined;
    }
}

/** Append the captured attribution params to a /wa/... URL. */
export function withAttribution(url) {
    const ft = getFirstTouch();
    const u = new URL(url, window.location.origin);
    const set = (k, v) => {
        if (v) u.searchParams.set(k, String(v).slice(0, 200));
    };
    set('utm_source', ft.utm_source);
    set('utm_medium', ft.utm_medium);
    set('utm_campaign', ft.utm_campaign);
    set('utm_content', ft.utm_content);
    set('utm_term', ft.utm_term);
    set('gclid', ft.gclid);
    set('fbclid', ft.fbclid);
    set('ref', ft.ref);
    set('lp', ft.lp);
    set('ph', posthogDistinctId());
    // return path + query (the CTA href is a relative link)
    return u.pathname + u.search;
}
