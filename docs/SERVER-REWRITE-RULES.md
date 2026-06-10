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

# Block install.php and install-helper.php
location ~ wp-admin/install(-helper)?\.php {
    deny all;
}

# Disable PHP execution in uploads
location ~* /(?:uploads|files)/.*\.php$ {
    deny all;
    access_log off;
    log_not_found off;
}

# Custom login URL (pretty URL mode only)
# Replace 'secure-login' with your configured slug
location ~ ^/secure-login/?$ {
    try_files $uri $uri/ /index.php?$args;
}

# END Fuerte-WP
```

### Notes

- The custom login `location` block is only needed when using **Pretty URL** mode (`/your-slug/`). Query parameter mode (`?your-slug`) works without it.
- Replace `secure-login` with the actual slug configured under **Settings → Fuerte-WP → Login Security**.
- After adding rules, reload nginx: `nginx -t && systemctl reload nginx`.

## Rule Reference

| Rule | Blocks | Why |
|---|---|---|
| `install(-helper)?\.php` | WordPress install wizards | Prevents unauthorized reinstallation |
| `uploads/.*\.php` | PHP execution in uploads | Stops malicious file uploads from executing |
| Custom login slug | Custom login rewrite | Routes `/secure-login/` to WordPress for login handling |
