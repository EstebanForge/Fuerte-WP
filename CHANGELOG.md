# Changelog

## Work in progress
- Added ability to suggest plugins for installation.
- Added ability to suggest discouraged plugins for removal, with alternatives to install.

## 1.1.1 / 2021-04-09
- Added support to control several WP's automatic emails.
- Added support to disable WP admin bar for specific roles.

## 1.1.0 / 2021-04-07
- WP Fuerte's configuration file now lives outside wp-config.php file, into his own wp-config-fuerte.php file. This to make it easier to deploy it to several WP installations, without the need to edit the wp-config.php file in all of them. Check the readme on how to install it.
- Added option to enable or disable strong passwords enforcing.
- Added support to prevent use of weak passwords.
- Added support for remove_menu_page.
- Added ability to disable WordPress's new Application Passwords feature.

## 1.0.1 / 2020-10-29
- Now using a proper Class.
- Added option to change WP sender email address.
- Added configuration to remove custom submenu items (remove_submenu_page).
- Force user creation and editing to use WP default strong password suggestion, for non super users.
- Prevent admin accounts creation or edition, for non super users.
- Customizable not allowed error message.

## 1.0.0 / 2020-10-27
- Initial release.
- Enable and force auto updates for WP core.
- Enable and force auto updates for plugins.
- Enable and force auto updates for themes.
- Enable and force auto updates for translations.
- Disables email triggered when WP auto updates.
- Change [WP recovery email](https://make.wordpress.org/core/2019/04/16/fatal-error-recovery-mode-in-5-2/) so WP crashes will go to a different email than the Administration Email Address in WP General Settings.
- Disables WP theme and plugin editor for non super users.
- Remove items from WP menu for non super users.
- Restrict editing or deleting super users.
- Disable ACF Custom Fields editor access for non super users.
- Restrict access to some pages inside wp-admin, like plugins or theme uploads, for non super users. Restricted pages can be extended vía configuration.
