<img src="https://checkout.limitloginattempts.com/assets/logo.png"
/>
# [LLAR software documentation](https://docs.limitloginattempts.com)
## Within this documentation, you'll find detailed guides, step-by-step tutorials, and in-depth explanations of the various features and capabilities offered by Limit Login Attempts Reloaded.

## Developer Notes

- The plugin now stores the current plugin version in options as `limit_login_plugin_version`.
- On plugin update (when version changes), LLAR fires:
  - `do_action( 'llar_plugin_version_updated', $old_version, $new_version )`
- MFA one-time **rescue** links: if the request looks like a prefetch or link preview, the response is a confirmation page (not 204) so the link is not consumed. The user submits **Click to continue** via POST (nonce bound to the rescue hash). The POST field name defaults to `llar_rescue_confirm`; override with `define( 'LLA_MFA_RESCUE_PREFETCH_BYPASS_ARG', 'my_key' );` before the plugin loads.
