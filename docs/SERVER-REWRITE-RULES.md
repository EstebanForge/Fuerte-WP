# Server Rewrite Rules

Fuerte-WP applies file-level security restrictions via web server config. Apache rules are auto-injected into `.htaccess` on activation. Nginx requires manual setup.

## Apache (Auto-Managed)

Fuerte-WP writes these rules to `.htaccess` on activation and removes them on deactivation. No manual action needed.

```apache
# BEGIN Fuerte-WP

# Block install.php and install-helper.php
<FilesMatch "^(wp-admin/)?(install|install-helper)\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Disable PHP execution in the uploads directory
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(wp-content/uploads/.*)\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|html|htm|shtml|sh|cgi|suspected)$ - [F,L]
</IfModule>

# END Fuerte-WP
```

> Source: `fuerte-wp.php` — `$fuertewp_htaccess` variable.

## Nginx (Manual)

Add these rules to your server block. Fuerte-WP cannot auto-write nginx config.

```nginx
# BEGIN Fuerte-WP

# Block WordPress install wizards.
# Covers both /install.php at the root and /wp-admin/install(-helper).php.
location ~ ^/(wp-admin/)?(install|install-helper)\.php$ {
    deny all;
}

# Block PHP and script execution in the uploads directory.
# Mirrors the full Apache extension list. Case-insensitive to catch
# .PHP, .Php, etc. on case-folding filesystems.
location ~* ^/wp-content/uploads/.*\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|html|htm|shtml|sh|cgi|suspected)$ {
    deny all;
    access_log off;
    log_not_found off;
}

# Custom login URL — pretty URL mode only.
# Replace 'secure-login' with your configured slug (Settings → Fuerte-WP → Login Security).
# OMIT this block if your server already has a catch-all:
#   location / { try_files $uri $uri/ /index.php?$args; }
location ~ ^/secure-login/?$ {
    try_files $uri $uri/ /index.php?$args;
}

# Block direct wp-login.php access at the server level.
# ONLY enable when Login URL Hiding is enabled. Comment out or remove
# when Login URL Hiding is disabled — WordPress needs direct access.
# location = /wp-login.php {
#     deny all;
# }

# END Fuerte-WP
```

### Notes

- The custom login `location` block is only needed when using **Pretty URL** mode (`/your-slug/`). Query parameter mode (`?your-slug`) works without it.
- Replace `secure-login` with the actual slug configured under **Settings → Fuerte-WP → Login Security**.
- After adding rules, reload nginx: `nginx -t && systemctl reload nginx`.
- The `wp-login.php` block is commented out by default. Enable it only when Login URL Hiding is active for server-level blocking before the request reaches PHP.

## Rule Reference

| Rule | Blocks | Why |
|---|---|---|
| `install(-helper)?\.php` | WordPress install wizards | Prevents unauthorized reinstallation |
| `uploads/.*\.(php\|...)` | Script execution in uploads | Stops malicious file uploads from executing |
| Custom login slug | Custom login rewrite | Routes `/secure-login/` to WordPress for login handling |
| `wp-login.php` (optional) | Direct login page access | Server-level short-circuit when login hiding is on |
