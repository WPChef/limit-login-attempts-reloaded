<img src="https://checkout.limitloginattempts.com/assets/logo.png" 
/>
# [LLAR software documentation](https://docs.limitloginattempts.com)
## Within this documentation, you'll find detailed guides, step-by-step tutorials, and in-depth explanations of the various features and capabilities offered by Limit Login Attempts Reloaded.

---

## Changes vs master (this branch)

### Onboarding: start from any plugin page

When onboarding is not completed yet (Cloud App not connected, `onboarding_popup_shown` not set), opening any LLAR admin page (e.g. Settings, Logs, Firewall) redirects to the **Dashboard** tab. That way onboarding can start from any plugin screen instead of only when the user lands on the dashboard first.

- **Hook:** `admin_init` at priority 5 (before output, so `wp_safe_redirect()` works).
- **Redirect target:** Dashboard tab of the plugin options page.
- **No redirect when:** user is already on dashboard, onboarding was shown, custom app is active, or app setup code is present.
