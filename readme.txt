=== Limit Login Attempts Reloaded ===
Contributors: wpchefgadget
Donate link: https://www.paypal.com/donate?hosted_button_id=FKD4MYFCMNVQQ
Tags: brute force, login, security, firewall, protection
Requires at least: 3.0
Tested up to: 6.3
Stable tag: 2.25.25

Block excessive login attempts and protect your site against brute force attacks. Simple, yet powerful tools to improve site performance.

== Description ==

Limit Login Attempts Reloaded functions as a robust deterrent against brute force attacks, bolstering your website's security measures and optimizing its performance. It achieves this by restricting the number of login attempts allowed. This applies not only to the standard login method, but also to XMLRPC, Woocommerce, and custom login pages. With more than 2.5 million active users, this plugin fulfills all your login security requirements.

The plugin functions by automatically preventing further attempts from a particular Internet Protocol (IP) address and/or username once a predetermined limit of retries has been surpassed. This significantly weakens the effectiveness of brute force attacks on your website. 

By default, WordPress permits an unlimited number of login attempts, posing a vulnerability where passwords can be easily deciphered through brute force methods.

**Limit Login Attempts Reloaded Premium (Try For 7 Days)**
Upgrade to our premium version to extend cloud-based protection to the Limit Login Attempts Reloaded plugin, thereby enhancing your login security. The premium version includes a range of highly beneficial features, including IP intelligence to detect, counter and deny malicious login attempts. Your failed login attempts will be safely neutralized in the cloud so your website can function at its optimal performance during an attack.  <a href="https://www.limitloginattempts.com/features/?from=wp-details-cta">Activate the premium version now</a> to fortify your login security using the most trusted login security plugin available!

https://www.youtube.com/watch?v=JfkvIiQft14

= Features (Free Version): =
* **Limit Logins** - Limit the number of retry attempts when logging in (per each IP).
* **Configurable Lockout Timings** - Modify the amount of time a user or IP must wait after a lockout. 
* **Remaining Tries** - Informs the user about the remaining retries or lockout time on the login page.
* **Lockout Email Notifications** - Informs the admin via email of lockouts. 
* **Denied Attempt Logs** - View a log of all denied attempts and lockouts. 
* **IP & Username Safelist/Denylist** - Control access to usernames and IPs. 
* **Sucuri** compatibility.
* **Wordfence** compatibility.
* **Ultimate Member** compatibility.
* **XMLRPC** gateway protection.
* **Woocommerce** login page protection.
* **Multi-site compatibility** with extra MU settings.
* **GDPR** compliant.
* **Custom IP origins support** (Cloudflare, Sucuri, etc.).

= Features (Premium Version): =
* **Performance Optimizer** - Offload the burden of excessive failed logins from your server to protect your server resources, resulting in improved speed and efficiency of your website.
* **Enhanced IP Intelligence** - Identify repetitive and suspicious login attempts to detect potential brute force attacks. IPs with known malicious activity are stored and used to help prevent and counter future attacks. 
* **Enhanced Throttling** - Longer lockout intervals each time a malicious IP or username tries to login unsuccessfully.
* **Deny By Country** - Deny IPs by country. 
* **Auto IP Denylist** - Automatically add IP addresses to your active cloud deny list that repeatedly fail login attempts. 
* **Global Denylist Protection** - Utilize our active cloud IP data from thousands of websites in the LLAR network. 
* **Synchronized Lockouts** -  Lockout IP data can be shared between multiple domains for enhanced protection in your network.
* **Synchronized Safelist/Denylist** - Safelist/Denylist IP and username data can be shared between multiple domains.
* **Premium Support** - Email support with a security tech.  
* **Auto Backups of All IP Data** - Store your active IP data in the cloud. 
* **Enhanced lockout logs** - Gain valuable insights into the origins of IPs that are attempting logins.
* **CSV Download of IP Data** - Download IP data direclty from the cloud. 
* **Supports IPV6 Ranges For Safelist/Denylist** 
* **Unlock The Locked Admin** - Easily unlock the locked admin through the cloud.

*Some features require higher level plans. Please <a href="https://www.limitloginattempts.com/pricing/?from=wp-details">view our pricing</a> for a full list of plans and features.  


= Upgrading from the old Limit Login Attempts plugin? =
1. Go to the Plugins section in your site's backend.
1. Remove the Limit Login Attempts plugin.
1. Install the Limit Login Attempts Reloaded plugin.

All your settings will be kept intact!

Many languages are currently supported in the Limit Login Attempts Reloaded plugin but we welcome any additional ones.

Help us bring Limit Login Attempts Reloaded to even more countries.

Translations: Bulgarian, Brazilian Portuguese, Catalan, Chinese (Traditional), Czech, Dutch, Finnish, French, German, Hungarian, Norwegian, Persian, Romanian, Russian, Spanish, Swedish, Turkish

Plugin uses standard actions and filters only.

Based on the original code from Limit Login Attempts plugin by Johan Eenfeldt.

= Branding Guidelines =
Limit Login Attempts Reloaded™ is a trademark of Atlantic Silicon Inc. When writing about the plugin, please make sure to use Reloaded after Limit Login Attempts. Limit Login Attempts is the old plugin.

* Limit Login Attempts Reloaded (correct)
* Limit Login Attempts (incorrect)

== Screenshots ==

1. Login screen after a failed login with remaining retries
2. Lockout login screen
3. Administration interface in WordPress 5.2.1

== Frequently Asked Questions ==

= What do I do if all users get blocked? =

If you are using contemporary hosting, it's likely your site uses a proxy domain service like CloudFlare, Sucuri, Nginx, etc. They replace your user's IP address with their own. If the server where your site runs is not configured properly (this happens a lot) all users will get the same IP address. This also applies to bots and hackers. Therefore, locking one user will lead to locking everybody else out. If the plugin is not using our <a href="https://www.limitloginattempts.com/features/">Cloud App</a>, this can be adjusted using the Trusted IP Origin setting. The cloud service intelligently recognizes the non-standard IP origins and handles them correctly, even if your hosting provider does not.

= How do I know if I'm under attack? =

An easy way to check if the attack is legitimate is to copy the IP address from the lockout notification, and go to https://whatismyipaddress.com/ip-lookup. Enter in the IP address to see if you recognize the location. If the location is not somewhere you recognize and you have received several failed login attempts, then you are likely being attacked. You might notice dozens or hundreds of IPs each day.

= How can I tell that the premium plugin is working? =

After you upgrade to our premium version, you will see a new dashboard in your WordPress admin that shows all attacks that will now relay through our cloud service.

You will still see the attacks because they will never stop regardless if you upgrade, but now our cloud service will safely process and neutralize them without taking resources from your site. This is very important and positively impacts your site’s stability and performance.

In some cases, you may notice an increase in speed and efficiency with your website. Also, a reduction in lockout notifications via email. 

= Could these failed login attempts be fake? =

Some users feel that it’s impossible to receive so many failed login attempts, especially since they’re site was just created or they have minimal human traffic. Let us be clear that these failed login attempts are NOT generated by the plugin. New websites are often hosted on a shared IP address, which makes it very easy for hackers to find. Also, new domain names are often crawled once they are created, so as soon as a WordPress website is built on it, it’s vulnerable to attacks. New websites are often the best target since security is not top of mind for site owners.

= What happens if my site exceeds the request limits in the plan? =

The premium plan’s resource limits start from 100,000 requests per month, which should accept almost any heavy brute-force attack. We monitor all of our sites and will alert the user if it appears they are going over their limits. If limits are reached, we will suggest to the user upgrading to the next plan. If you are using the free version, the load caused by brute force attacks will be absorbed by your current hosting bandwidth, which could cause your hosting costs to increase.

= What do I do if all users get blocked? =

If you are using contemporary hosting, it’s likely your site uses a proxy domain service like CloudFlare, Sucuri, Nginx, etc. They replace your user’s IP address with their own. If your server is not configured properly, all users will get the same IP address. This also applies to bots and hackers. Therefore, locking one user will lead to locking everybody else out. In the free version of the plugin, this can be adjusted using the Trusted IP Origin setting. In the premium version, the cloud service intelligently recognizes the non-standard IP origins and handles them correctly, even if your hosting provider does not.

= What URLs are being attacked and protected? =

The URLs being protected are your login page (wp-login.php, wp-admin), xmlrpc.php, WooCommerce login page, and any custom login page you have that uses regular WordPress login hooks.

= Why is LLAR more popular than other brute-force protection plugins? =

Our main focus is protecting your site from brute-force attacks. This allows our plugin to be very lean and effective. It doesn’t require a lot of your web hosting resources and keeps your site well-protected. More importantly, it does all of this automatically as our service learns on its own about each IP it encounters. In contrast, a firewall would require manual addition or removal of IPs. We’ve published an article about it <a href="https://www.limitloginattempts.com/should-i-block-ip-addresses/">here</a>.

= What to do when an admin gets blocked? =

Open the site from another IP. You can do this from your cell phone, or using Opera browser and enabling free VPN there. You can also try turning off your router for a few minutes and then see if you get a different IP address. These will work if your hosting server is configured correctly. If that doesn’t work, connect to the site using FTP or  your hosting control panel file manager. Navigate to wp-content/plugins/ and rename the limit-login-attempts-reloaded folder. Log in to the site then rename that folder back and whitelist your IP. By upgrading to our premium app, you will have the unlocking functionality right from the cloud so you’ll never have to deal with this issue. 

= What settings should I use In the plugin? =

The settings are explained within the plugin in great detail. If you are unsure, use the default settings as they are the recommended ones.

= Can I share the safelist/denylist throughout all of my sites?=

By default, you will need to copy and paste the lists to each site manually. For the <a href="https://www.limitloginattempts.com/features/?from=wp-details">premium service</a>, sites are grouped within the same private cloud account. Each site within that group can be configured if it shares its lockouts and access lists with other group members. The setting is located in the plugin's interface. The default options are recommended.

== Changelog ==

= 2.25.25 =
* PHP 8.2/9 compatibility improved, thanks to Jer Turowetz!
* Button size and text typo fixed.

= 2.25.24 =
* Better loading of translations.
* Fixed PHP warning related to menu.

= 2.25.23 =
* Better side menu.
* Fixed I18N issues, thanks to alexclassroom!

= 2.25.22 =
* Interface changes.
* Tested with WP 6.3.

= 2.25.21 =
* Optimization: autoload for large options turned off.
* Interface changes.

= 2.25.20 =
* Fix against network requests caching removed b/c some misconfigured servers can't handle it.

= 2.25.19 =
* Better handling of network connection issues.
* Fixed responsive formatting on dashboard.
* Added fix against network requests caching.

= 2.25.18 =
* Fixed errors occurring in situations where two versions of the plugin are installed (which should not normally happen).

= 2.25.17 =
* Refactoring.
* Server load reducing optimization.

= 2.25.16 =
* Double slashes in paths removed.
* Better handling of cloud response codes.

= 2.25.15 =
* Error messages logic fixed.

= 2.25.14 =
* Multisite support improved.
* CSS outside of the plugin issue fixed.
* Better number formatting on the dashboard.
* Lockout email template updated.

= 2.25.13 =
* Ultimate Member compatibility.
* Fixed conflicting URL parameters in some rare cases.
* Updated attempts counter logic.

= 2.25.12 =
* Fixed IPv4 validation when passed with a port number.
* Fixed texts and translations.

= 2.25.11 =
* PHP 8 compatibility fixed.
* Logs loading issue fixed.
* Help and Extensions tabs added.
* Notification about auto updates added.
* Displaying of plugin version added.
* Text changes made.

= 2.25.10 =
* Tested with PHP 8.
* Small styles refactoring.
* Fixed a rare issue with events log not being displayed correctly.
* Chart library updated.

= 2.25.9 =
* Welcome page replaced with a modal.

= 2.25.8 =
* Email text, links updated.

= 2.25.7 =
* Country flags added to log.
* Refresh button added to log.
* Email text updated.

= 2.25.6 =
* Email links updated.

= 2.25.5 =
* Fixed Woocommerce integration.
* Updated some interface links.

= 2.25.4 =
* Fixed session error in rare cases.
* Access rules explained.
* Improved session behavior on the login page.
* Fixed warning on some GoDaddy installations.

= 2.25.3 =
* Improved compatibility with WordFence.
* Better handling of HTTP_X_FORWARDED_FOR on Debug tab.
* Added option to hide warning badge.

= 2.25.2 =
* Security indicator fixed for multisite.

= 2.25.1 =
* Added setting to turn the dashboard widged off.
* The widget is visible to admins only.

= 2.25.0 =
* Dashboard widged added.
* Security indicator added.

= 2.24.1 =
* Fixed E_ERROR occurring in rare cases when the log table is corrupted.

= 2.24.0 =
* Protection increased: bots can't parse lockout messages anymore.

= 2.23.2 =
* Cloud: better unlock UX.
* Litle cleanup.

= 2.23.1 =
* Added infinite scroll for cloud logs.

= 2.23.0 =
* Reduced plugin size by removing obsolete translations.
* Cleaned up the dashboard.
* Cloud: added information about auto/manually-blocked IPs.
* GDPR: added an option to insert a link to a Privacy Policy page via a shortcode, clarified GDPR compliance.

= 2.22.1 =
* IP added to the email subject.

= 2.22.0 =
* Added support of CIDR notation for specifying IP ranges.
* Texts updated.
* Refactoring.

= 2.21.1 =
* Fixed: Uncaught Error: Call to a member function stats()
* Cloud API: added block by country.
* Refactoring.

= 2.21.0 =
* GDPR compliance: IPs obfuscation replaced with a customizable consent message on the login page.
* Cloud API: fixed removing of blocked IPs from the access lists under certain conditions.
* Cloud API: domain for Setup Code is taken from the WordPress settings now.

= 2.20.6 =
* Multisite tab links fixed.

= 2.20.5 =
* Option to show and hide the top-level menu item.

= 2.20.4 =
* Sucuri compatibility verified.
* Wordfence compatibility verified.
* Better menu navigation.
* Timezones fixed for the global chart.

= 2.20.3 =
* More clear wording.
* Cloud API: fixed double submit in the settings form.
* Better displaying of stats.

= 2.20.2 =
* Updated email text.

= 2.20.1 =
* New dashboard more clear stats.

= 2.20.0 =
* New dashboard with simple stats.

= 2.19.2 =
* Texts and links updated.

= 2.19.1 =
* Welcome page.
* Image and text updates.

= 2.19.0 =
* Refactoring.
* Feedback message location fixed.
* Text changes.

= 2.18.0 =
* Cloud API: usage chart added.
* Text changes.

= 2.17.4 =
* Missing jQuery images added.
* PHP 5 compatibility fixed.
* Custom App setup link replaced with setup code.

= 2.17.3 =
* Plugin pages message.

= 2.17.2 =
* Lockout notification refactored.

= 2.17.1 =
* CSS cache issue fixed.
* Notification text updated.

= 2.17.0 =
* Refactoring.
* Email text and notification updated.
* New links in the list of plugins.

= 2.16.0 =
* Custom Apps functionality implemented. More details: https://limitloginattempts.com/app/

= 2.15.2 =
* Alternative method of closing the feedback message.

= 2.15.1 =
* Refactoring.

= 2.15.0 =
* Reset password feature has been removed as unwanted.
* Small refactoring.

= 2.14.0 =
* BuddyPress login error compatibility implemented.
* UltimateMember compatibility implemented.
* A PHP warning fixed.

= 2.13.0 =
* Fixed incompatibility with PHP < 5.6.
* Settings page layout refactored.

= 2.12.3 =
* The feedback message is shown for admins only now, and it can also be closed even if the site has issues with AJAX.

= 2.12.2 =
* Fixed the feedback message not being shown, again.

= 2.12.1 =
* Fixed the feedback message not being shown.

= 2.12.0 =
* Small refactoring.
* get_message() - fixed error notices.
* This is the first time we are asking you for a feedback.

= 2.11.0 =
* Blacklisted usernames can't be registered anymore.

= 2.10.1 =
* Fixed: GDPR compliance option could not be selected on the multisite installations.

= 2.10.0 =
* Debug information has been added for better support.

= 2.9.0 =
* Trusted IP origins option has been added.

= 2.8.1 =
* Extra lockout options are back.

= 2.8.0 =
* The plugin doesn't trust any IP addresses other than _SERVER["REMOTE_ADDR"] anymore. Trusting other IP origins make protection useless b/c they can be easily faked. This new version provides a way of secure IP unlocking for those sites that use a reverse proxy coupled with misconfigurated servers that populate _SERVER["REMOTE_ADDR"] with wrong IPs which leads to mass blocking of users.

= 2.7.4 =
* The lockout alerts can be sent to a configurable email address now.

= 2.7.3 =
* Settings page is moved back to "Settings".

= 2.7.2 =
* Settings are moved to a separate page.
* Fixed: login error message. https://wordpress.org/support/topic/how-to-change-login-error-message/

= 2.7.1 =
* A security issue inherited from the ancestor plugin Limit Login Attempts has been fixed.

= 2.7.0 =
* GDPR compliance implemented.

* Fixed: ip_in_range() loop $ip overrides itself causing invalid results.
https://wordpress.org/support/topic/ip_in_range-loop-ip-overrides-itself-causing-invalid-results/

* Fixed: the plugin was locking out the same IP address multiple times, each with a different port.
https://wordpress.org/support/topic/same-ip-different-port/

= 2.6.3 =
* Added support of Sucuri Website Firewall.

= 2.6.2 =
* Fixed the issue with backslashes in usernames.

= 2.6.1 =
* Plugin returns the 403 Forbidden header after the limit of login attempts via XMLRPC is reached.

* Added support of IP ranges in white/black lists.

* Lockouts now can be released selectively.

* Fixed the issue with encoding of special symbols in email notifications.

= 2.5.0 =
* Added Multi-site Compatibility and additional MU settings. https://wordpress.org/support/topic/multisite-compatibility-47/

= 2.4.0 =
* Usernames and IP addresses can be white-listed and black-listed now. https://wordpress.org/support/topic/banning-specific-usernames/ https://wordpress.org/support/topic/good-831/
* The lockouts log has been inversed. https://wordpress.org/support/topic/inverse-log/

= 2.3.0 =
* IP addresses can be white-listed now. https://wordpress.org/support/topic/legal-user/
* A "Gateway" column is added to the lockouts log. It shows what endpoint an attacker was blocked from. https://wordpress.org/support/topic/xmlrpc-7/
* The "Undefined index: client_type" error is fixed. https://wordpress.org/support/topic/php-notice-when-updating-settings-page/

= 2.2.0 =
* Removed the "Handle cookie login" setting as they are now obsolete.
* Added bruteforce protection against Woocommerce login page attacks. https://wordpress.org/support/topic/how-to-integrate-with-woocommerce-2/
* Added bruteforce protection against XMLRPC attacks. https://wordpress.org/support/topic/xmlrpc-7/

= 2.1.0 =
* The site connection settings are now applied automatically and therefore have been removed from the admin interface.
* Now compatible with PHP 5.2 to support some older WP installations.

= 2.0.0 =
* fixed PHP Warning: Illegal offset type in isset or empty https://wordpress.org/support/topic/limit-login-attempts-generating-php-errors
* fixed the deprecated functions issue
https://wordpress.org/support/topic/using-deprecated-function
* Fixed error with function arguments: https://wordpress.org/support/topic/warning-missing-argument-2-5
* added time stamp to unsuccessful tries on the plugin configuration page.
* fixed .po translation files issue.
* code refactoring and optimization.