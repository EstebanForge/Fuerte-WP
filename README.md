# Fuerte-WP

<p align="center">
	<img src="https://github.com/EstebanForge/Fuerte-WP/blob/master/.wp-org-assets/icon-256x256.png?raw=true" alt="Fuerte-WP Logo" />
</p>

Stronger WP. Limit access to critical WordPress areas, even for other admins.

Fuerte-WP is a WordPress Plugin to enforce certain limits for users with wp-admin administrator access, and to force some other security related tweaks into WordPress.

Available at the official [WordPress.org plugins repository](https://wordpress.org/plugins/fuerte-wp/).

## Why?

Because even if you choose to set an user only as Editor, some plugins require users to be an Administrator. And so many Administrators without limits could become an issue, security-wise.

Not only because admins can edit every single configuration inside WordPress. Administrators can also upload plugins or themes, or even edit plugins and theme files (on by default), and with those capabitilies, compromise your WordPress installation.

Fuerte-WP will limit some administrators from access critical WordPress areas that you can define.

Fuerte-WP auto-protect itself and cannot be disabled, unless your account is declared as super user, or you have access to the server (FTP, SFTP, SSH, cPanel/Plesk, etc.).

## Login Security Deep Dive

Fuerte-WP's Login Security system provides comprehensive protection against brute force attacks and unauthorized access attempts:

### 🛡️ Attack Prevention
- **Rate Limiting**: Configurable thresholds for failed login attempts (default: 5 attempts in 15 minutes)
- **Progressive Lockouts**: Increasing lockout durations for repeated security violations
- **IP & Username Tracking**: Track and block based on both IP addresses and usernames
- **Real-time Monitoring**: Live dashboard showing current login attempts and active lockouts

### 📊 Monitoring & Management
- **Detailed Logging**: Comprehensive logs of all security events with timestamps and user agents
- **AJAX Dashboard**: Real-time updates without page refreshes
- **Export Functionality**: Export security data for external analysis or backup
- **Individual Unblock**: Unblock specific IPs or usernames without clearing all data

### 🇪🇺 GDPR Compliance
- **Privacy Notices**: Customizable GDPR compliance messages on login and registration forms
- **Default Messaging**: Built-in privacy notice template if no custom message is provided
- **Non-Intrusive Design**: Messages displayed below forms without affecting user experience

### 🔐 Optional: Login URL Obscurity
*Security by obscurity - disabled by default for optimal security*

For users who want additional obscurity layers, Fuerte-WP offers optional login URL hiding:

- **Hide wp-login.php**: Prevents direct access to the default WordPress login URL
- **Custom Login Endpoints**: Use either pretty URLs (`/secure-login/`) or query parameters (`?secure-login`)
- **WP-Admin Protection**: Automatically blocks direct `/wp-admin/` access for unauthorized users
- **Smart Redirection**: Configure custom redirect URLs for blocked login attempts

**Note**: This feature is disabled by default because true security comes from strong authentication and monitoring, not hiding URLs. Enable only if you understand the trade-offs.

## Features

### 🛡️ Login Security
- **Rate Limiting & Lockouts**: Configurable thresholds for failed login attempts with automatic IP lockouts
- **Real-time Monitoring**: AJAX-powered dashboard for monitoring login attempts and managing lockouts
- **GDPR Privacy Notice**: Customizable privacy compliance message displayed on login/registration forms
- **Hidden Field Validation**: Enhanced CSRF protection with hidden form validation
- **Invalid Login Redirect**: Configure where unauthorized login attempts are redirected (404 page or custom URL)
- **Login URL Obscurity** (Optional): Obscure your WordPress login URL by hiding `wp-login.php` and `/wp-admin/` access (security by obscurity, disabled by default)

### 🔐 Access Control & Restrictions
- **Super User System**: Configure users who bypass all restrictions and maintain full access
- **Role-Based Restrictions**: Limit what different administrator roles can access and modify
- **Plugin & Theme Protection**: Prevent installation, deletion, and editing of plugins/themes by non-super users
- **Menu Management**: Remove or restrict access to specific WordPress admin menu items
- **Page Access Control**: Restrict access to sensitive WordPress admin areas
- **User Account Protection**: Prevent editing or deletion of super user accounts
- **ACF Integration**: Restrict access to Advanced Custom Fields editor interface

### ⚙️ WordPress Core Tweaks
- **Auto-Update Management**: Configurable automatic updates for core, plugins, themes, and translations
- **API Security**: Disable XML-RPC, Application Passwords, and restrict REST API access
- **Email Configuration**: Customize WordPress recovery and sender email addresses
- **Security Hardening**: Disable file editors, force strong passwords, and block weak password usage
- **Admin Bar Control**: Disable WordPress admin bar for specific user roles
- **Customizer Restrictions**: Lock down Customizer features like CSS editor and theme modifications

### 🚀 Performance & Monitoring
- **Login Logging**: Comprehensive logging of all login attempts, failed authentications, and security events
- **Export Capabilities**: Export security data and logs for analysis
- **Database Optimization**: Automated cleanup and maintenance of security logs
- **Cron-Based Updates**: Background auto-updates that don't impact site performance

### 🔧 Developer Features
- **File-Based Configuration**: Support for `wp-config-fuerte.php` for mass deployment
- **Configuration Caching**: Optimized performance with intelligent caching
- **Hook System**: Extensible architecture with comprehensive WordPress hook integration
- **Multisite Support**: Compatible with WordPress multisite installations

## How to install

1. Install Fuerte-WP from WordPress repository. Plugins > Add New > Search for: Fuerte-WP. Activate it.
2. Configure Fuerte-WP at Settings > Fuerte-WP.
3. **Setup Login Security**: Configure your custom login URL and review security settings.
4. **Configure Super Users**: Add your email address to the super users list to maintain full access.
5. **Review Restrictions**: Customize which admin areas and features to restrict for other administrators.
6. Enjoy enhanced WordPress security!

### Harder configuration (optional)

Fuerte-WP allows you to configure it "harder". This way, Fuerte-WP options inside wp-admin panel aren't even shown at all. Useful to mass deploy Fuerte-WP configuration to multiple WordPress installations.

To use the harder configuration, follow this steps:

- Download a copy of [```config-sample/wp-config-fuerte.php```](https://github.com/EstebanForge/Fuerte-WP/blob/master/config-sample/wp-config-fuerte.php) file, and set it up with your desired settings. Edit and tweak the configuration array as needed.

- Upload your tweaked ```wp-config-fuerte.php``` file to your WordPress's root directory. This usually is where your wp-config.php file resides.

- When Fuerte-WP detects that file, it will load the configuration from it. This will bypass the DB values from the options page, completely.

#### Config file updates

To check if your ```wp-config-fuerte.php``` file need an update, follow this steps:

Check the default [```config-sample/wp-config-fuerte.php```](https://github.com/EstebanForge/Fuerte-WP/blob/master/config-sample/wp-config-fuerte.php) file. The header of the sample config will have the version when it was last modified.

Then check out your own ```wp-config-fuerte.php``` file. If yours has a lower version number, then you need to update your settings array.

Compare your config with the [default wp-config-fuerte.php file](https://github.com/EstebanForge/Fuerte-WP/blob/master/config-sample/wp-config-fuerte.php) and add the new/missing settings to your file. You can use [Meld](https://meldmerge.org), [WinMerge](https://winmerge.org), [Beyond Compare](https://www.scootersoftware.com), [Kaleidoscope](https://kaleidoscope.app), [Araxis Merge](https://www.araxis.com/merge/), [Diffchecker](https://www.diffchecker.com) or any similar software diff to help you here.

Upload your updated ```wp-config-fuerte.php``` to your WordPress's root directory and replace the old one.

Don't worry. New Fuerte-WP features that need new configuration values will not run or affect you until you upgrade your config file and add the new/missing settings.

## FAQ

Check the [full FAQ here](https://github.com/EstebanForge/Fuerte-WP/blob/master/FAQ.md).

## Suggestions, Support

Please, open [a discussion](https://github.com/EstebanForge/Fuerte-WP/discussions).

## Bugs and Error reporting

Please, open [an issue](https://github.com/EstebanForge/Fuerte-WP/issues).

## Changelog

[Available here](https://github.com/EstebanForge/Fuerte-WP/blob/master/CHANGELOG.md).
