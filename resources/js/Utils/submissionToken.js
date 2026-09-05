// Crockford base32 alphabet (excludes I, L, O, U) — 26 chars total,
// matching the backend's `size:26` + `/^[0-9A-HJKMNP-TV-Z]{26}$/` rule
// (App\Http\Requests\CustomerPortalClassificationRequest and friends).
const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'

/**
 * Generates a client-side idempotency key ("submission_token") for one
 * logical customer action (submit / retry / confirm). Callers must
 * generate this ONCE per action instance (e.g. on component mount, not
 * inside the click handler) so that a double-click or a network-retry of
 * the same click reuses the same token — that is exactly what makes the
 * backend's idempotency guarantee effective.
 */
export function generateSubmissionToken() {
    let time = Date.now()
    let timeChars = ''
    for (let i = 0; i < 10; i++) {
        timeChars = ENCODING[time % 32] + timeChars
        time = Math.floor(time / 32)
    }

    let randomChars = ''
    for (let i = 0; i < 16; i++) {
        randomChars += ENCODING[Math.floor(Math.random() * 32)]
    }

    return timeChars + randomChars
}
