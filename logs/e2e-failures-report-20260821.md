# E2E failures report — 2026-08-21

Source: Playwright `test-results/.last-run.json` + traces (file stdout log was truncated).

**Totals:** 282 tests · **5 failed** · ~95 intentional skips (not counted as failures)

## Plugin (2) — T008 / no UltimateMemberIntegration

| Test | Spec | Evidence |
|------|------|----------|
| Registration is blocked for blacklisted email | ultimate-member-registration-protection-cloud.spec.js | Expected LLAR «Registration is currently disabled.» — registration succeeded (profile «Test User»). Cloud ACL not called on UM registration. |
| Registration is blocked for already registered email deny | same | Expected LLAR disabled message — got UM «The email you entered is incorrect». Deny/ACL did not intercept before UM. |

**Action:** implement / enable `UltimateMemberIntegration` (README T008). Fix in plugin, not tests.

## Test suite (3)

| Test | Spec | Evidence |
|------|------|----------|
| Registration is blocked for lockout | ultimate-member-registration-protection-cloud.spec.js | Timeout on `input[name="log"]` at `/login/` (UM form). Need UM helpers (`usernameField`), not wp-login fields. After selector fix, plugin may still fail without UM integration. |
| Lost password is allowed for whitelisted username | woocommerce-lost-password-protection-local.spec.js | `guest-allow-name` is not a WP user. Test expects success banner; WC correctly returns «Invalid username or email.» Email twin already expects Invalid. |
| Lost password is allowed for pass-listed username | same | Same defect for `guest-pass-name`. |

**Action:** fix lockout selectors; align username allow/pass expectations with email variants (or seed real users).

## Note

Intentional skips (trial-ui, UM login cloud, premium lost-password, info/MC UI, etc.) = unimplemented features in tested plugin build — not failures of this run.
