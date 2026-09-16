# E2E comparison: WordPress 7.1 vs previous run

**Date:** 2026-08-21  
**WP:** pinned `wordpress:7.1-php8.2-apache` (`$wp_version = '7.1'`, PHP 8.2)  
**Suite:** 282 tests, docker compose e2e  
**Note:** Site was already on WP 7.1 before the pin; Dockerfile now pins 7.1 explicitly. Clean `down -v` + rebuild.

## Baseline (previous full run, ~00:23)

| Metric | Value |
|--------|-------|
| Failed | **5** |
| Plugin (T008 UM cloud registration) | 2 |
| Test defects | 3 (WC guest username expectations; UM lockout wp-login selectors) |

Failed then:
1. Registration is blocked for blacklisted email — **plugin**
2. Registration is blocked for already registered email deny — **plugin**
3. Registration is blocked for lockout — **test** (wrong selectors)
4. Lost password is allowed for whitelisted username — **test**
5. Lost password is allowed for pass-listed username — **test**

## WP 7.1 run (13:15–13:43)

| Metric | Value |
|--------|-------|
| Failed | **3** |
| Plugin (T008) | **3** |
| Test defects | **0** |

Failed now (all `ultimate-member-registration-protection-cloud.spec.js`):
1. Registration is blocked for lockout — **plugin** (selectors fixed; registration still completes)
2. Registration is blocked for blacklisted email — **plugin**
3. Registration is blocked for already registered email deny — **plugin**

## Diff

| Change | Detail |
|--------|--------|
| Fixed by prior test commit `73f8d48` | WC guest allow/pass username → expect Invalid + guest-login cleanup in `test.php` — **no longer fail** |
| Lockout | No longer fails on `input[name=log]`; reaches assertion → same T008 plugin gap |
| New WP 7.1 regressions | **None observed** vs previous suite outcome (aside from test fixes already landed) |
| Unchanged | ~95 intentional skips; T008 trio still red |

## Verdict

WordPress 7.1 does not introduce new e2e failures relative to the previous run. Net improvement (−2 failures) comes from test-suite fixes, not from WP itself. Remaining 3 failures are known plugin gaps (no `UltimateMemberIntegration` on UM registration / cloud ACL).
