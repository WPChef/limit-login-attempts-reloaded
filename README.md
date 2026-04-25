<img src="https://checkout.limitloginattempts.com/assets/logo.png"
/>
# [LLAR software documentation](https://docs.limitloginattempts.com)
## Within this documentation, you'll find detailed guides, step-by-step tutorials, and in-depth explanations of the various features and capabilities offered by Limit Login Attempts Reloaded.

## Developer Notes

- The plugin now stores the current plugin version in options as `limit_login_plugin_version`.
- On plugin update (when version changes), LLAR fires:
  - `do_action( 'llar_plugin_version_updated', $old_version, $new_version )`
