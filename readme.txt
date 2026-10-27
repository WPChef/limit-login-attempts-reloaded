=== Limit Login Attempts Security – Brute Force Protection, 2FA, Firewall & Login Security ===
Contributors: wpchefgadget, nikitaglobal
Donate link: https://www.paypal.com/donate?hosted_button_id=FKD4MYFCMNVQQ
Tags: brute force, login security, security, firewall, 2FA
License: GPLv2 or later
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 3.3.11

Limit login attempts and stop brute force attacks. WordPress security with 2FA, firewall, bot protection, IP blocking, and secure login monitoring.

== Description ==

Limit login attempts, stop brute force attacks, and protect your WordPress login with one of the most trusted WordPress security plugins, used on 2 million websites.

<a href="https://www.limitloginattempts.com">Limit Login Attempts Security</a> adds brute force protection, two-factor authentication (2FA), a login firewall, bot protection, and IP blocking to the most targeted page on your site: the login page. It is lightweight, works automatically with the recommended settings, and won't slow down your website.

https://www.youtube.com/watch?v=S3nf8Zpbcfs

= Why WordPress Login Security Matters =

By default, WordPress allows unlimited login attempts, so bots can guess usernames and passwords until one works. With AI-powered tools, attackers can run brute force attacks faster and more effectively than ever.

Most WordPress hacks and malware infections start with a compromised login. Limit Login Attempts Security closes that door by stopping brute force attacks, bot logins, credential stuffing, XML-RPC attacks, and WooCommerce login abuse before an attacker ever gets in. For complete website security, pair it with a malware scanner. It is compatible with the popular ones.

= Brute Force Protection & Limit Login Attempts =

* Limit login attempts by IP address and by username
* Automatically lock out suspicious IPs and usernames
* Adjustable retry limits and lockout durations
* Protect wp-login.php and wp-admin from automated attacks
* Failed login protection with retry warnings for real users

= Two-Factor Authentication (2FA / MFA) =

Built-in two-factor authentication adds a second verification step to WordPress logins, so a stolen or guessed password alone isn't enough. Secure login for administrators and users with no extra plugin required.

= WordPress Firewall & Bot Protection =

A lightweight login firewall that blocks malicious login requests, detects suspicious behavior, and reduces bot-based login attacks without the overhead of a full security suite.

= XML-RPC Protection =

Attackers use xmlrpc.php to try many passwords in a single request. XML-RPC protection applies the same login limits there, so this backdoor can't bypass your brute force protection.

= IP Blocking & Access Controls =

* IP safelist and denylist (IP blocking), including IPv6 ranges
* Username safelist and denylist
* Custom trusted IP origin for Cloudflare and reverse proxy setups

= Login Monitoring & Notifications =

* Failed login attempt logs
* Lockout email notifications
* Denied attempt tracking

= WooCommerce, Multisite & Plugin Compatibility =

Protects WooCommerce login pages, custom login forms that use standard WordPress login hooks, and WordPress multisite networks. Compatible with Wordfence, Sucuri, Ultimate Member, MemberPress, WPS Hide Login, and Cloudflare.

= Premium: Cloud Login Security (Free 14-Day Trial) =

<a href="https://www.limitloginattempts.com/plans/">Upgrade to Limit Login Attempts Security Premium</a> to move brute force protection to the cloud:

* Real-time malicious IP intelligence and a global denylist
* Country blocking for logins
* Offload failed login traffic from your server during attacks
* Enhanced throttling and lockout escalation
* Registration page protection and successful login tracking
* Lockout analytics with geolocation data
* Synchronized lockouts, safelists, and denylists across all your sites
* Cloud backups of IP data and CSV exports
* Unlock yourself from the cloud dashboard
* Priority support from security specialists

= Lightweight WordPress Security =

Unlike large security suites, Limit Login Attempts Security focuses on login security and brute force protection. That means faster performance, less server overhead, and easier setup, whether you run a blog, a WooCommerce store, a membership site, an agency, or an enterprise WordPress network.

= Upgrading from the Original Limit Login Attempts Plugin? =

1. Remove the old Limit Login Attempts plugin
2. Install Limit Login Attempts Security
3. Your settings remain intact

= Translations =

Available in Spanish, French, German, Dutch, Turkish, Swedish, Russian, Romanian, Chinese (Traditional), Brazilian Portuguese, and more.

== Frequently Asked Questions ==

= How does limiting login attempts stop brute force attacks? =

A brute force attack tries thousands of password combinations. Once an IP or username exceeds the allowed number of failed logins, it is locked out, which makes guessing a password impractical. Learn more about how to <a href="https://www.limitloginattempts.com/brute-force-attack-protection-the-best-tools-tips-to-keep-your-website-safe/">prevent brute force attacks</a>.

= Does it include two-factor authentication (2FA)? =

Yes. Two-factor authentication (2FA/MFA) is built into the free version and adds a second verification step to the WordPress login.

= Is this a WordPress firewall? =

It is a login-focused firewall. It blocks malicious login requests, bots, and denylisted IPs at the login page. Premium adds cloud-based IP intelligence and country blocking.

= Does it protect against malware and hacks? =

It does not scan files for malware. It prevents the most common way sites get hacked and infected, which is a compromised login. For full website security, use it alongside a malware scanner. It is compatible with Wordfence and Sucuri.

= What URLs are being attacked and protected? =

Your login page (wp-login.php, wp-admin), xmlrpc.php, the WooCommerce login page, and any custom login page that uses standard WordPress login hooks.

= Will it slow down my website? =

No. It focuses only on login protection, which keeps it lightweight. With Premium, failed login traffic is handled in the cloud, which reduces server load during attacks.

= Is it compatible with Wordfence and other security plugins? =

Yes. It works alongside Wordfence, Sucuri, WPS Hide Login, Ultimate Member, MemberPress, and Cloudflare. The Debug tab includes a compatibility table for popular plugins.

= What do I do if all users get blocked? =

Your site likely sits behind a proxy such as Cloudflare, Sucuri, or Nginx that replaces visitors' IPs with its own, so every visitor appears to have the same IP. Adjust the Trusted IP Origin setting, or use our <a href="https://www.limitloginattempts.com/plans/">Cloud App</a>, which detects non-standard IP origins automatically.

= What do I do when an admin gets blocked? =

Open the site from another IP, for example on your phone's cellular connection or a VPN. If that doesn't work, use FTP or your hosting file manager to rename the wp-content/plugins/limit-login-attempts-reloaded folder, log in, rename it back, and safelist your IP. Premium users can unlock themselves from the cloud dashboard.

= How do I know if I'm under attack? =

Copy the IP from a lockout notification and check its location with an IP lookup tool. Several failed logins from locations you don't recognize indicate an attack. Dozens or hundreds of IPs per day is common.

= Could these failed login attempts be fake? =

No. The plugin doesn't generate them. New sites are often on shared IPs and newly registered domains are crawled quickly, which makes them easy targets. Read more about <a href="https://www.limitloginattempts.com/could-these-failed-login-attempts-be-fake/">fake login attempts in WordPress</a>.

= What settings should I use? =

The default settings are recommended. Each setting is explained in detail within the plugin.

= How can I tell that the premium plugin is working? =

A new dashboard in your WordPress admin shows requests and failed login attempts relayed through our cloud service. You may also notice fewer lockout emails and better site performance.

= What happens if my site exceeds the request limits in the plan? =

Premium plans start at 100,000 requests per month, enough for almost any heavy brute force attack. We'll alert you if you approach your limit and suggest the next plan. On the free version, attack traffic is absorbed by your hosting, which could raise hosting costs.

= Can I share the safelist/denylist across all of my sites? =

On the free version, copy the lists to each site manually. With <a href="https://www.limitloginattempts.com/plans/?from=wp-details">Premium</a>, sites in the same cloud account can share lockouts and access lists.

== Changelog ==

= 3.3.11 =
* Added a red dot to the admin menu and admin bar when Micro Cloud requests are exhausted.

= 3.3.10 =
* Added detection of a cached or optimizer-stripped login page and an admin notice warning that it can break the email code (MFA) login flow.
* Fixed the onboarding Micro Cloud step silently completing on activation errors; it now shows the server error message so users learn when the program is closed or trial is unavailable.
* Added a compatibility table to the Debug tab showing which LLAR features work with popular third-party plugins.
* Redesigned the lockout notification email.

= 3.3.9 =
* Reworked the premium tab upgrade CTAs to show plan-specific pricing and adapt the upgrade links to the currently installed plan.
* Added Micro Cloud usage banners that show the monthly requests used and warn when the quota is almost exhausted.
* Updated the lockout notification email with a new premium upgrade call to action.
* Fixed the cloud Active Lockouts widget showing too few lockouts by auto-loading additional pages when a short batch did not fill the list.
* Fixed the local denylist still blocking logins in cloud mode after the cloud ACL allowed them; local rules now apply only as a failover when the cloud ACL is unavailable.
* Fixed WooCommerce customers being unable to register during checkout when cloud registration limiting was enabled; account creation is now allowed during checkout while standalone registration forms stay protected.

= 3.3.8 =
* Added Personal and Business plans to the premium comparison table and made the displayed plans adapt to the currently installed plan.
* Aligned dashicons across the logs, premium, ACL and help admin pages for WordPress 7 compatibility.
* Fixed the failed-login page script being blocked under a nonce-based Content Security Policy by printing it with wp_get_inline_script_tag(). Thanks to Oskar Schöldström (@oxyc) for the pull request.
* Fixed the denylist matching the account email when only the username was listed; now the submitted login is matched literally (case-insensitive), while explicit email entries still apply.

= 3.3.7 =
* Fixed PHP warnings when a cloud app custom setting is missing the label, description, or value field.
* Added the SameSite=Lax attribute to the login flow cookie for better CSRF protection.
* Fixed dashicons line-height on all admin pages and dashboard widgets for WordPress 7 compatibility.
* Fixed the cloud app setup so it verifies the setup code was saved before activating the custom app, preventing an inconsistent state on storage errors.
* Fixed the review admin notice buttons not working because its inline script was being stripped by output sanitization.
* Fixed the Micro Cloud setup so it surfaces the actual server error message to admins and handles an incomplete app configuration gracefully instead of failing silently.

= Earlier versions =
For the changelog of earlier versions, please refer to the <a href="https://plugins.svn.wordpress.org/limit-login-attempts-reloaded/trunk/changelog.txt">changelog.txt</a> file.
