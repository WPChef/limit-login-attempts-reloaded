# ADR-0001: Surface `/checkout/network` API error messages to the user

- **Status:** Accepted
- **Date:** 2026-08-11
- **Decision owners:** LLAR team

## Context

When a user creates a Micro Cloud (MC) subscription, the plugin sends a request
to the `/checkout/network` endpoint. The request carries a `group` field:

- `group=free` — standard free Micro Cloud signup.
- `group=trial` — trial signup.

The backend may reject the request with a non-200 status for business reasons
that must be communicated to the user, for example:

- The Micro Cloud program is temporarily closed.
- The user is not eligible to start another trial.

Previously, when `/checkout/network` returned an error, the user saw either a
generic "server is not working" message (modal flow) or nothing at all
(onboarding popup flow). The API-provided `message` field was discarded.

## Decision

Surface the `message` field from the `/checkout/network` error response body to
the user **as-is** in every place Micro Cloud is created. No additional
business logic is layered on top — the only behaviour change is that the error
text reaches the UI.

### Implementation points

1. **HTTP transport (`HttpTransportFopen`)** — set `ignore_errors => true` in the
   stream context. Without this, `fopen` discards the response body on 4xx/5xx
   statuses, making the `message` field unreadable. This is the critical fix:
   the body must be available even on error statuses.

2. **AJAX callback (`Ajax::activate_micro_cloud_callback`)** — handle the
   non-200 branch explicitly:
   - Decode the response body JSON.
   - Extract `message` (string). If absent, fall back to a generic localized
     message via `micro_cloud_fallback_error_message()`.
   - Return `wp_send_json_error(['msg' => $message])`.
   - The success path (200 + `setup_code`) is unchanged.

3. **UI — `micro-cloud-modal.php`** — the `.catch()` handler reads
   `response.data.msg` and writes it into the
   `.llar-upgrade-subscribe_notification__error .llar-mc-error-message` span.

4. **UI — `onboarding-popup.php`** — the `.catch()` handler shows the message
   via `$.alert({ content: message, type: 'red' })` (previously silent).

### Entry points covered

Both UI entry points funnel through the single AJAX handler
`wp_ajax_activate_micro_cloud` → `activate_micro_cloud_callback`:

- Dashboard CTA → Micro Cloud modal (`micro-cloud-modal.php`).
- Onboarding popup upgrade step (`onboarding-popup.php`).

There is one backend handler, so the error-surfacing logic lives in one place.

## Consequences

- Users now see the exact API-provided reason when MC creation fails (program
  closed, trial denied, etc.).
- The `ignore_errors` change in `HttpTransportFopen` applies to **all** requests
  routed through the fopen transport, not just MC. This is safe: it only means
  the body is now readable on error statuses (previously it was lost). Callers
  that check `$response['status']` are unaffected; callers that only checked
  `$response['error']` may now want to also inspect the body.

## Testing

E2E specs: `llar-e2e-tests/e2e/specs/dashboard/micro-cloud-network-errors.spec.js`

The cloud-mock `mc` profile controls the `/checkout/network` response:

| Profile        | HTTP | `message`                                          |
|----------------|------|----------------------------------------------------|
| `ok`           | 200  | (returns `setup_code`, success path)              |
| `closed`       | 403  | "The Micro Cloud program is currently closed."    |
| `trial-denied` | 403  | "You are not eligible to start another trial."    |

Both error profiles assert the API message appears in the modal and the success
notification is hidden.
