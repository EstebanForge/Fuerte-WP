# Project Overview

Fuerte-WP is a WordPress security plugin that limits access to critical WordPress areas, even for administrator users. It enforces security restrictions and provides administrative controls to manage WordPress installations more securely.

## Architecture

### Core Components

1. **Main Plugin File** (`fuerte-wp.php:1`): Plugin initialization, autoloading, and activation/deactivation hooks
2. **Core Class** (`includes/class-fuerte-wp.php:33`): Main plugin orchestrator that manages loaders and hooks
3. **Enforcer** (`includes/class-fuerte-wp-enforcer.php:20`): Security enforcement engine that applies all restrictions and rules
4. **Admin Interface** (`admin/class-fuerte-wp-admin.php`): WordPress admin panel and settings using Carbon Fields
5. **Data Storage** (`includes/class-fuerte-wp-carbon-fields-datastore.php`): Custom datastore for configuration

### Key Architecture Patterns

- **Singleton Pattern**: Enforcer class uses singleton pattern via `get_instance()` method
- **Loader System**: `Fuerte_Wp_Loader` class manages all WordPress hooks and filters
- **Configuration Management**: Supports both database options and file-based configuration (`wp-config-fuerte.php`)
- **Transient Caching**: Configuration is cached using WordPress transients for performance
- **Cronjob-based Updates**: Auto-updates are managed via WordPress cronjobs with configurable frequency

### Configuration System

The plugin supports two configuration methods:
1. **Database Configuration**: Stored via Carbon Fields theme options
2. **File Configuration**: Via `wp-config-fuerte.php` in WordPress root directory (overrides database)

Configuration structure:
```php
$fuertewp = [
    'status' => 'enabled',
    'super_users' => ['email@domain.com'],
    'general' => [
        'access_denied_message' => 'Access denied.',
        'recovery_email' => '',
        'sender_email_enable' => true,
        'sender_email' => '',
        'autoupdate_core' => true,
        'autoupdate_plugins' => true,
        'autoupdate_themes' => true,
        'autoupdate_translations' => true,
        'autoupdate_frequency' => 'twelve_hours', // six_hours, twelve_hours, daily, twodays
    ],
    'tweaks' => [...],
    'restrictions' => [...],
    'emails' => [...],
    'restricted_scripts' => [...],
    'restricted_pages' => [...],
    'removed_menus' => [...],
    'removed_submenus' => [...],
    'removed_adminbar_menus' => [...]
];
```

## Auto-Update System

### Cronjob-Based Updates
The auto-update system uses WordPress cronjobs instead of direct filters:
- **Cron Hook**: `fuertewp_trigger_updates`
- **Frequency Options**: 6 hours, 12 hours, 24 hours, or 48 hours (configurable)
- **Method**: `Fuerte_Wp_Enforcer::trigger_updates()` applies filters dynamically during execution
- **Benefits**: More controlled update timing, reduces server load during normal page requests

### Update Process
1. Configuration is read from database or file
2. Cronjob is registered with specified frequency
3. During cron execution, filters are temporarily applied
4. `wp_maybe_auto_update()` is called to perform updates
5. Filters are automatically removed after execution

## Development Commands

### Setup and Dependencies
```bash
# Install dependencies (Carbon Fields)
composer install

# Update dependencies
composer update
```

### Deployment
```bash
# Deploy with version tag (creates SVN tag)
./deploy.sh

# Deploy without version tag (updates trunk only)
./deploy-notag.sh
```

### Development Notes
- Plugin uses Carbon Fields for admin interface (`vendor/htmlburger/carbon-fields`)
- Autoloading is handled via Composer
- Configuration validation happens in `Fuerte_Wp_Enforcer::config_setup()`
- Plugin self-protects from deactivation by non-super users
- Auto-updates use cronjobs for better performance and control

## Security Features Implementation

### Core Security Controls
- **Super User System**: Users bypass restrictions based on email addresses
- **Access Control**: Blocks access to sensitive WordPress admin areas
- **Menu/Plugin Restrictions**: Removes admin menu items and blocks plugin access
- **File Editing**: Disables theme/plugin editors via `DISALLOW_FILE_EDIT`
- **REST API Controls**: Restricts REST API access and disables application passwords

### Email Management
- **Recovery Email**: Redirects WordPress recovery emails to configured address
- **Sender Email**: Customizes WordPress email sender to match domain
- **Notification Filtering**: Disables various WordPress admin notifications

### Auto Updates (Cronjob-Based)
- **Configurable Frequency**: Users can choose update check intervals
- **Selective Updates**: Core, plugins, themes, and translations can be enabled independently
- **Cron Management**: Automatic registration and cleanup of scheduled tasks
- **Performance**: Updates run in background without affecting page load times

## Testing Environment

The plugin includes development constants in the config file:
```php
define('FUERTEWP_DISABLE', false);    // Enable/disable plugin
define('FUERTEWP_FORCE', false);      // Force restrictions even for super users
```

## File Structure Conventions

- **Classes**: Follow WordPress plugin coding standards
- **Hooks**: All hooks registered via the loader system
- **Internationalization**: Uses WordPress i18n functions with text domain 'fuerte-wp'
- **Security**: All user input properly escaped and validated
- **Admin Interface**: Uses Carbon Fields for consistent UI components

## Important Implementation Details

1. **Self-Protection**: Plugin cannot be deactivated by non-super users and hides its admin interface
2. **Configuration Caching**: Uses transients with version-based cache invalidation
3. **Apache Integration**: Automatically adds .htaccess rules for upload directory security
4. **Elementor Compatibility**: Handles conflicts with Elementor page builder
5. **WordPress Version Compatibility**: Supports WordPress 6.0+ and PHP 7.3+
6. **Cronjob Updates**: Auto-updates run via scheduled tasks for better performance

## Code Style Guidelines

- Follow WordPress Coding Standards
- Use proper escaping functions (`esc_url()`, `esc_html()`, etc.)
- Implement proper capability checks
- Use WordPress hooks and filters appropriately
- Maintain backward compatibility where possible
- Use descriptive method and variable names
- Add proper PHPDoc comments for all public methods
