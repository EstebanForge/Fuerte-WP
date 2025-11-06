=== Fuerte-WP ===
Contributors: tcattd
Tags: security, login, protection, admin, brute-force, GDPR, privacy, access-control, multisite
Stable tag: 1.7.0
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Fortify your WordPress site with military-grade security. Stop brute-force attacks, hide your login URL, and control admin access like never before.

== Description ==

🛡️ **ULTIMATE WORDPRESS SECURITY SOLUTION**

Is your WordPress site vulnerable to attacks? Every day, thousands of sites get compromised through weak login security, unrestricted admin access, and exposed login URLs. Fuerte-WP is your fortress against these threats.

**⚠️ STARTLING FACT:**
- 90% of hacked WordPress sites are compromised through brute-force attacks on wp-login.php
- Most WordPress security breaches happen from within - by administrator accounts with too much power
- Your default wp-login.php URL is a public invitation to attackers

**🔥 WHY FUERTE-WP IS DIFFERENT:**

Most security plugins just alert you AFTER an attack. Fuerte-WP PREVENTS attacks before they happen, combining multiple layers of protection that work together seamlessly.

**🚨 BRUTE-FORTRESS™ ATTACK PREVENTION**
- **Intelligent Rate Limiting**: Configurable thresholds (default: 5 attempts in 15 minutes)
- **Progressive Lockouts**: Smart lockouts that get longer with repeated attempts
- **IP & Username Blacklisting**: Automatic blocking of suspicious IPs and usernames
- **Real-Time Threat Detection**: Live dashboard showing current attacks and active lockouts

**👑 ADMINISTRATOR CONTROL SYSTEM**
- **Super User Access**: Designate who has full access (YOU) while restricting others
- **Role-Based Permissions**: Granular control over what different admin roles can do
- **Plugin & Theme Protection**: Prevent other admins from installing, deleting, or modifying critical files
- **Menu Management**: Hide sensitive WordPress menu items from restricted users
- **User Account Shielding**: Protect super user accounts from being edited or deleted

**📊 SECURITY COMMAND CENTER**
- **Live Attack Monitoring**: Real-time AJAX dashboard shows login attempts as they happen
- **Detailed Forensic Logs**: Comprehensive logging with timestamps, IPs, and user agents
- **Export Security Data**: Download logs for analysis or compliance reporting
- **Smart Notifications**: Get alerted about security events and lockouts
- **One-Click Management**: Instantly unblock IPs, clear logs, or reset lockouts

**🇪🇺 GDPR COMPLIANCE MADE EASY**
- **Privacy Notice Builder**: Customizable GDPR compliance messages for login/registration forms
- **Built-in Legal Templates**: Professional default privacy messages if you don't customize
- **Non-Intrusive Design**: Compliance that doesn't hurt user experience
- **Audit Trail**: Logging that helps with GDPR compliance requirements

**⚙️ ADVANCED WORDPRESS HARDENING**
- **Auto-Update Management**: Automated updates for core, plugins, themes, and translations
- **API Security Shield**: Disable XML-RPC, Application Passwords, and restrict REST API
- **Email Protection**: Customize WordPress recovery and sender emails
- **Security Hardening**: Force strong passwords, disable file editors, block weak passwords
- **Performance Optimized**: Background updates that don't slow down your site

**🔐 OPTIONAL: LOGIN URL OBSCURITY**
*For users who want additional obscurity layers*

- **Invisible Login URL**: Replace default `wp-login.php` with custom URLs
- **Smart Redirection**: Send attackers away from your site (404 page or custom URL)
- **WP-Admin Fortress**: Block direct `/wp-admin/` access to unauthorized users
- **Hidden Field Protection**: Advanced CSRF protection against automated attacks

*Note: This feature is disabled by default because true security comes from strong authentication, not hiding URLs.*

**🔒 WHY CHOOSE FUERTE-WP?**

✅ **PROACTIVE PROTECTION** - Stops attacks BEFORE they succeed
✅ **INTELLIGENT RATE LIMITING** - Real-time attack detection and prevention
✅ **ADMIN INSIDER THREAT PROTECTION** - Controls what other administrators can do
✅ **GDPR READY** - Built-in privacy compliance features
✅ **PERFORMANCE OPTIMIZED** - Won't slow down your website
✅ **MULTISITE COMPATIBLE** - Works on single sites and WordPress networks
✅ **SELF-PROTECTING** - Cannot be disabled by non-super users
✅ **DEVELOPER FRIENDLY** - File-based configuration for mass deployment
✅ **SMART SECURITY APPROACH** - Focuses on real protection over security by obscurity

**🎯 PERFECT FOR:**
- Multi-author blogs and news sites
- Client websites built by agencies
- E-commerce stores with multiple administrators
- Educational institutions with WordPress installations
- Enterprise WordPress deployments
- Anyone serious about WordPress security

**⚡ INSTALL IN SECONDS, PROTECT FOR YEARS**

Don't wait for your site to get hacked. Install Fuerte-WP today and join thousands of smart WordPress administrators who sleep better at night knowing their sites are fortified.

== Installation ==

1. Click "Install Now" or search for "Fuerte-WP" in your WordPress dashboard
2. Activate the plugin
3. Visit Settings > Fuerte-WP to configure your security fortress
4. **CRITICAL**: Add your email as a Super User to maintain full access
5. Setup your custom login URL (takes 30 seconds)
6. Review and customize your security restrictions
7. Congratulations! Your WordPress site is now fortified.

🚨 **IMPORTANT**: After activation, immediately add your email address to the Super Users list to ensure you maintain full administrative access.

== Frequently Asked Questions ==

= Is this plugin safe for beginners? =
Absolutely! Fuerte-WP is designed with smart defaults. Simply install, add yourself as a super user, and you're protected. Advanced features are optional.

= Will this slow down my website? =
No! Fuerte-WP is optimized for performance with intelligent caching and background processing. You won't notice any speed difference.

= What if I get locked out? =
Super users can never be locked out. Always add your email to the Super Users list immediately after installation.

= Does this work with multisite networks? =
Yes! Fuerte-WP is fully compatible with WordPress multisite installations and can be network-activated.

= Can other administrators disable this plugin? =
No! Fuerte-WP self-protects and can only be disabled by super users or users with server access (FTP, SSH, etc.).

= Is GDPR compliance included? =
Yes! Built-in privacy notices and logging help with GDPR compliance requirements.

= Do I need technical knowledge? =
Basic WordPress knowledge is sufficient. The interface is intuitive with helpful explanations for every feature.

= What about support? =
We offer excellent support through GitHub discussions. Documentation and FAQs are available for self-help.

= Is my login URL really hidden? =
Yes! Your wp-login.php becomes inaccessible, and attackers are redirected away from your site.

= Can I customize the restrictions? =
Absolutely! Every security feature can be customized to fit your specific needs.

= What if I forget my custom login URL? =
Super users can still access wp-admin directly. Always keep your super user email safe!

== Screenshots ==

1. **Security Dashboard** - Real-time monitoring of login attempts and security events
2. **Login Security Settings** - Configure custom login URLs and protection settings
3. **Super User Configuration** - Manage who has full access to your WordPress site
4. **Access Control Panel** - Customize restrictions for different administrator roles
5. **Live Attack Monitoring** - Watch security events unfold in real-time
6. **GDPR Compliance Settings** - Configure privacy notices and compliance features

== Changelog ==

= 1.7.0 / 2025-11-06 =
🚀 **MAJOR SECURITY UPDATE**

**NEW LOGIN SECURITY FEATURES:**
- ✨ **Login URL Hiding** - Hide wp-login.php and wp-admin from attackers
- ✨ **Custom Login URLs** - Use pretty URLs or query parameters for login
- ✨ **Brute-Force Protection** - Rate limiting and automatic IP lockouts
- ✨ **Real-Time Monitoring** - Live dashboard showing login attempts
- ✨ **GDPR Privacy Notices** - Customizable compliance messages
- ✨ **Attack Logging** - Comprehensive security event logging
- ✨ **Export Capabilities** - Download security data for analysis

**ENHANCEMENTS:**
- 🔧 Improved admin interface with better organization
- 🔧 Enhanced configuration caching for better performance
- 🔧 Better multisite compatibility
- 🔧 Optimized database queries and logging
- 🔧 Updated user interface with clearer security indicators

**SECURITY IMPROVEMENTS:**
- 🛡️ Added hidden field validation for login forms
- 🛡️ Enhanced CSRF protection mechanisms
- 🛡️ Improved IP detection and blocking
- 🛡️ Better handling of proxy and CDN configurations
- 🛡️ Strengthened protection against automated attacks

**BUG FIXES:**
- 🐛 Fixed GDPR message display duplication
- 🐛 Resolved configuration caching issues
- 🐛 Fixed redirect handling for custom login URLs
- 🐛 Improved compatibility with various hosting environments
- 🐛 Enhanced error handling and logging

Previous changelog entries available at [GitHub](https://github.com/EstebanForge/Fuerte-WP/blob/master/CHANGELOG.md).

== Upgrade Notice ==

= 1.7.0 =
🚨 **MAJOR SECURITY UPGRADE** - This release adds powerful new login security features! After upgrading, please visit Settings > Fuerte-WP to configure your custom login URL and review the new security features. Your WordPress site will be more secure than ever before!