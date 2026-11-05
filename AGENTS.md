# Fuerte-WP — Agent Knowledge Base

**Updated:** 2026-09-11

## OVERVIEW
WordPress security plugin. Limits access to critical WP areas, even for admins. Enforces restrictions, manages 2FA, auto-updates, login security, email controls.

- **Type:** `wordpress-plugin` (single-file entry: `fuerte-wp.php`)
- **Requires:** PHP 8.2+, WordPress 6.5+ (tested to 6.9)
- **License:** GPL-2.0+
- **Author:** Esteban Cuevas `<esteban@attitude.cl>`

## STACK
- **PHP** 8.2+ (strict_types implied by WPCS; `defined('ABSPATH') || die()` guard pattern)
- **Composer** for autoloading + 1 runtime dep: `estebanforge/hyperfields` (admin UI framework, prefixed under `FuerteWpDep\` via Strauss into `vendor-prefixed/`)
- **HyperFields** (`vendor/estebanforge/hyperfields/`) replaces the former Carbon Fields; admin settings tabs are built on it
- **Bundled Two-Factor lib** at `includes/two-factor/` (regenerated from upstream, DO NOT EDIT)
- **Pest 4** + **Brain Monkey 2** + **PHPUnit 12** for tests
- **php-cs-fixer** for formatting

## STRUCTURE
```
fuerte-wp.php                      # Entry: constants, config load, autoload bootstrap
includes/
  class-fuerte-wp.php              # Core orchestrator (loads deps, wires hooks, runs enforcer)
  class-fuerte-wp-enforcer.php     # Security engine (singleton) — ALL restrictions/rules
  class-fuerte-wp-config.php       # Config loader (file > DB), transient-cached
  class-fuerte-wp-dot-files.php    # Filesystem kill switches (.fuertewp-disable, .fuertewp-disable-mfa)
  class-fuerte-wp-hook-manager.php # Static hook registration + conditional gating
  class-fuerte-wp-helper.php       # is_super_user(), IP/CIDR utilities
  class-fuerte-wp-two-factor.php   # Wrapper: boots bundled Two-Factor lib
  class-fuerte-wp-updraft-guard.php # UpdraftCentral kill switch (opt-out)
  class-fuerte-wp-auto-update-manager.php
  class-fuerte-wp-login-manager.php / -logger / -url-hider
  class-fuerte-wp-loader.php       # Hook queue (actions/filters)
  class-fuerte-wp-logger.php
  two-factor/                      # SYNCED UPSTREAM LIB — never edit
  helpers.php                      # Procedural helpers
  views/                           # PHP view partials
admin/
  class-fuerte-wp-admin.php        # HyperFields-based admin UI (tabs, checkboxes)
  css/ js/ partials/
public/                            # Frontend-facing hooks (mostly no-op, kept for structure)
config-sample/wp-config-fuerte.php # Sample file-config + escape-hatch docs
tests/
  unit/*.php                       # Pest unit tests (Brain Monkey mocks)
  Integration/*.php
  bootstrap.php                    # Test bootstrap (mind the hardcoded api.php path, see Gotchas)
  wordpress-mocks.php
scripts/
  sync-two-factor.sh               # Re-pull bundled Two-Factor lib
  build-release.sh / deploy.sh / deploy-readme-only.sh
  bump-version.sh
languages/                         # .pot + es_CL/es_ES translations
docs/                              # Bundling, deployment, FAQ, server rewrites
```

## COMMANDS
Run from `src/app/plugins/fuerte-wp/` (NOT the docker repo root — that `composer.json` manages WP core).

| Action | Command |
|---|---|
| Install dev deps | `composer install` |
| Install prod deps (no dev) | `composer production` |
| Run tests | `composer test` |
| Tests with coverage | `composer test:coverage` |
| Lint one file | `php -l includes/<file>.php` |
| Format | `composer cs:fix` |
| Re-sync Two-Factor lib | `composer sync-two-factor` |
| Bump version | `composer version-bump` |
| Deploy (prod build + SVN) | `composer deploy` |

In the Docker dev env (from repo root): `./wp <command>` proxies WP-CLI into the container.

## RELEASE CYCLE
Target: wordpress.org (SVN deploy) plus a git tag. **No GitHub release** — the tag only; do not run `build-release.sh`/`gh release create` as part of the cycle.

1. `composer test` green; `php -l` on touched files.
2. Commit all feature work first — `deploy.sh` reads `Version:` from the default branch and refuses a dirty tree.
3. `composer production` BEFORE the bump: the prod `vendor/` build must land in the version-bump commit, not after it.
4. `composer version-bump` → `scripts/bump-version.sh` rewrites the `Version:` header in `fuerte-wp.php` plus `Stable tag:` in `README.txt` and `README.md` in one pass.
5. Add the `CHANGELOG.md` entry for X.Y.Z: a `# X.Y.Z / YYYY-MM-DD` section above the last one, house style `**New Feature** / **Changed** / **Bug Fix** / **Security** / **Note**` bullets, most important first. WP.org reads the changelog for its Users tab.
6. Commit `chore(release): X.Y.Z` (version bump + changelog + prod vendor).
7. `composer deploy` → prompts for the WP.org SVN username, checks readme version == plugin header, commits `trunk/`, then creates `tags/X.Y.Z` in the plugin SVN. Readme-only changes: `scripts/deploy-readme-only.sh` instead.
8. `git tag X.Y.Z && git push origin X.Y.Z`.

The vendored HyperFields upstream release is handled separately by the maintainer; it is not part of this cycle.

## CONFIGURATION SYSTEM (critical to understand)
Two sources, one normalization, transient-cached. **File wins over database.**

1. **File** (`wp-config-fuerte.php` in `ABSPATH`): defines a `$fuertewp` array. Loaded by `fuerte-wp.php` before boot.
2. **Database** (`fuertewp_settings` option, persisted by HyperFields admin UI): normalized by `Fuerte_Wp_Config::normalize_settings()`.

Flow (`Fuerte_Wp_Config::get_config()`):
- Transient key `fuertewp_config` is checked first (cache).
- Cache bypassed on settings page or via `$bypass_cache=true`.
- `load_from_file()` → if empty, `load_from_database()`.
- **Gotcha:** after editing enforcer/config logic that reads the normalized array, bust the cache: `wp eval 'delete_transient("fuertewp_config");'` or the stale value persists.

Escape hatches (defined in `wp-config.php` or `wp-config-fuerte.php`):
- `FUERTEWP_DISABLE` (true) — plugin returns early, never boots.
- `FUERTEWP_FORCE` (true) — enforce restrictions even for super users.
- `FUERTEWP_DISABLE_2FA` (truthy) — skip bundled Two-Factor lib load.
- Filesystem tier (server ops, see `Fuerte_Wp_Dot_Files`): marker files `.fuertewp-disable` (plugin never boots, checked first in `fuerte-wp.php`) and `.fuertewp-disable-mfa` (2FA stays off). Searched in ABSPATH and its parent (docroot), wp-config.php style. Presence only; empty file counts; checked before every constant. Parent-directory markers are site-wide on shared hosting: sibling sites under one parent share the switch.

Config shape (normalized): `$fuertewp['general']`, `['super_users']`, `['tweaks']`, `['restrictions']`, `['emails']`, `['restricted_scripts']`, `['restricted_pages']`, `['removed_menus']`, `['removed_submenus']`, `['removed_adminbar_menus']`, `['login_security']`.

## SUPER USERS
`Fuerte_Wp_Helper::is_super_user($user, $respect_force)` — match by **email** (case-insensitive) against `$fuertewp['super_users']`. Super users bypass restrictions unless `FUERTEWP_FORCE`. This is the recovery lever; document it in `config-sample/`.

## TWO-FACTOR (bundled)
Wrapper `includes/class-fuerte-wp-two-factor.php`, class `Fuerte_Wp_TwoFactor`. Key invariants:
- `boot()` runs on `plugins_loaded` priority 1. Loads the bundled lib **only if** `Two_Factor_Core` is not already declared, the standalone plugin isn't active, and `FUERTEWP_DISABLE_2FA` is not truthy. Exactly one copy ever runs (avoids class-redeclare fatal).
- **Provider policy:** `DISABLED_PROVIDERS` constant (`Two_Factor_Dummy`) strips via `two_factor_providers` filter at priority 20. Enabled: Email, TOTP, Backup Codes.
- **Enforcement (admins):** read-only. `enforce_email_for_admins()` injects Email via `two_factor_enabled_providers_for_user` filter at priority 20. **Never writes user meta** — disabling enforcement instantly releases admins.
- **Super-user bypass:** `is_enforced_user()` checks `is_super_user()` first. Super users never get auto-enforced.
- **Admin toggle:** `login_security.two_factor_enable` (default on) and `two_factor_enforce` (default on).
- Never edit `includes/two-factor/` — regenerate via `composer sync-two-factor`.

## STREAM INTEGRATION (activity digest)
`Fuerte_Wp_Stream_Activity` + `Fuerte_Wp_Stream_Connector`, booted procedurally at fuerte-wp.php include (same pattern as Updraft Guard). Gated on `class_exists('WP_Stream\Plugin')`.
- **Hook timing is load-bearing:** Stream loads classes at plugins_loaded 20; its Connectors instantiation applies the `wp_stream_connectors` filter at init 9. Our filter is added at plugins_loaded 30; the connector file itself is require'd inside the filter callback (at apply time), because `WP_Stream\Connector` does not exist earlier. Never "simplify" these guards.
- Digest cron runners CANNOT go through Fuerte_Wp_Hook_Manager: it only initializes inside Fuerte_Wp_Enforcer::run(), which exits early on foreign cron events. Runners attach in boot().
- Master toggle `fuertewp_stream_enable` (default OFF) gates everything: tab data viewer, digest crons, connector registration. Weekly default frequency; daily/weekly/monthly (custom `monthly` cron schedule registered via cron_schedules).
- schedule_digest() is init-time on every request: it must NOT clear+reschedule blindly (WP-Cron option churn + Sunday skip: on Sundays strtotime('next sunday 00:00') points at NEXT week, so clearing a due-but-unfired event silently skips the digest). Current contract: clear all three only when master is off; otherwise compute the desired hook/recurrence/target, leave a due-or-past event untouched, and only clear+reschedule when wp_next_scheduled differs from the target. `daily` MUST stay an explicit case — folding it into `default:` under a weekly default broke daily scheduling once already.
- Tab queries Stream tables `{base_prefix}stream`(+`_meta`): updates=installer/updated, lifecycle=installer/installed|activated|deactivated|deleted, new_admins=users/created + stream_meta roles LIKE %Administrator% (user_role holds the ACTOR's role there), admin_passwords=users/password-reset + user_role=administrator. Logins/failed logins come from fuertewp_login_attempts, not Stream. Core updates are context "WordPress" (capital W).
- Digest email: previous-day/week/month window (UTC bounds, converted with wp_date at query time; Stream stores site-local datetimes). Tab viewer uses a rolling trailing 24h instead.
- HyperFields saves the whole page as one `update_option('fuertewp_settings', ...)`; the connector hooks `update_option_fuertewp_settings` (change-only) and logs changed keys. Stream's own settings connector ALSO logs per-key records for this option in some contexts — expect both shapes in the log. Connector sets register_frontend=true on purpose: CLI (`wp option update`), cron and REST writes must be logged too; Stream skips register_frontend=false connectors outside is_admin().
- Legacy `|||`/`_fuertewp_*` options and `_carbon_fields_theme_options_fuerte-wp|...` CF3 keys are folded into fuertewp_settings by Fuerte_Wp_Migrator::migrate() at boot (kept as the settings-preservation mechanism after the Carbon shim retirement, 1.14.0). migrate() is NOT gated on is_admin() (fuerte-wp.php): frontend/REST/cron read fuertewp_settings too, and with the legacy fallback gone an unmigrated read would serve defaults and sit in the config transient for 24h.

## CARBON FIELDS SHIM (retired 1.14.0)
Removed from Fuerte_Wp_Config: `load_from_legacy_database()`, `load_multiselect_field()`, `save_multiselect_field()`, `array_filter_recursive()`, and the legacy fallbacks/dual-writes in get_field()/set_field(). The $is_multiselect params survive as inert caller-compat. Fuerte_Wp_Migrator + `cleanup_duplicate_patterns()` stay (fold-in + Data Tools safety net).
- normalize_settings() emits flat aliases `deferred_plugins`/`deferred_themes`/`blocked_plugins`/`blocked_themes` — consumers and tests read the flat top-level lists, not only the deferred_updates group.
- load_from_database() returns normalize_settings() UNCONDITIONALLY (empty storage = full defaults). Do not reintroduce an empty-storage early return; it nulls every key.
- The bundled Two-Factor lib is excluded from BOTH fixer configs — cs:fix reformatting it caused three regressions. Traps: `.php-cs-fixer.php` SHADOWS `.php-cs-fixer.dist.php` (the fixer ignores the dist when the local one exists, so dist-only exclusions are inert), and Symfony Finder matches excludes relative to the `->in()` roots (`two-factor` under `includes/`, `includes/two-factor` under `__DIR__`). If cs:fix ever "Fixed N" and touched the lib, revert with `git checkout -- includes/two-factor/` and check which config was read.

## UPDRAFTCENTRAL GUARD
`Fuerte_Wp_Updraft_Guard` boots procedurally at fuerte-wp.php include time (after the `FUERTEWP_DISABLE` return) so its filters exist before UpdraftPlus attaches its Central host at `plugins_loaded` priority 10 (verified against UpdraftPlus free 1.26.11 and Pro 2.26.11.26: `UpdraftPlus::plugins_loaded()` → `central/factory.php` → `central/bootstrap.php`; identical code in both).
Opt-out setting: `restrictions.block_updraftcentral` (HyperFields key `fuertewp_restrictions_block_updraftcentral`, default on). The guard reads the file-config global `$fuertewp` first, then the DB option, mirroring get_config() file-wins semantics.
Three layers: udrpc-shaped request deny (403 exit), `updraftcentral_host_plugins` wiped at `FUERTEWP_LATE_PRIORITY`, and read+write blinding of the three connection-key options at that same late priority (`pre_option_*`, `pre_site_option_*`, `pre_update_option_*`, `pre_update_site_option_*`, plus `updraftplus_get_option` for the UpdraftPlus_Options/multisite bucket).
Backups keep working; remote control, migration receiving, and UpdraftPlus Vault disconnects die.
Already-stored keys stay in the database: blinded while protected, they revive if the protection is removed (opt-out, `FUERTEWP_DISABLE`, `.fuertewp-disable`, plugin removal). Purge by revoking in the UpdraftCentral dashboard and deleting the three options.
`FUERTEWP_DISABLE` or a `.fuertewp-disable` marker also removes this protection.
Safe alongside the standalone mu-plugin kill switch: duplicate filters are idempotent and no symbols are shared.

## EMAIL MANAGEMENT
Hooked in `Fuerte_Wp_Hook_Manager::register_email_hooks()`:
- `wp_mail_from` / `wp_mail_from_name` — sender rewrite (gated on `general.sender_email_enable`).
- `recovery_mode_email` — toggle on: redirect to the configured recovery address (callback replaces only the recipient, keeps the rest of the core email array). Toggle off: suppress the email entirely.
- Notification toggles (`emails[...]`, `true` = send / `false` = suppress; every hook below is a real WP core filter, verified against vendored core 7.0): fatal_error switches the recovery redirect for suppression, application_password_created (ships WP 7.2, core.trac 63582 / ticket #63927, default on, gated on `has_action('wp_create_application_password', 'wp_application_password_created_notification')` — core's own default-filters.php registration, so a reshipped/renamed feature keeps us inert; absent from released core until 7.2) -> `wp_send_application_password_created_email`, automatic_updates -> `auto_core_update_send_email` + `auto_plugin_update_send_email` + `auto_theme_update_send_email`, comment_awaiting_moderation -> `notify_moderator`, comment_has_been_published -> `notify_post_author`, user_reset_their_password -> `wp_password_change_notification_email`, user_confirm_personal_data_export_request -> `user_request_confirmed_email_to`, new_user_created -> `wp_new_user_notification_email_admin`, network_new_site_created -> `send_new_site_email`, network_new_site_activated -> `wpmu_welcome_notification`, network_new_user_site_registered -> `pre_site_option_registrationnotification` (no core bool filter exists for `newuser_notify_siteadmin()`; the plugin short-circuits core's own registrationnotification gate, which also silences `newblog_notify_siteadmin()` self-service signup notices).
- Suppression callback: `Fuerte_Wp_Enforcer::suppress_email_notification()` — bool filters get `false`, content-array filters get `'to' => ''`, recipient-string filters get `''`. `wp_mail()` safely rejects an empty recipient.
- **2FA token emails are NOT intercepted** — `Two_Factor_Email::send_code()` calls `wp_mail()` directly.

`Fuerte_Wp_Enforcer::sender_email_address()`: empty `sender_email` config falls back to `no-reply@<home_url host>`. Treat empty strings as unset (fixed 1.10.0).

## AUTO-UPDATES (cron-based, not direct filters)
- Cron hook `fuertewp_trigger_updates`, configurable frequency (6h/12h/24h/48h).
- `Fuerte_Wp_Enforcer::trigger_updates()` applies update filters during cron, calls `wp_maybe_auto_update()`, removes filters after.
- Per-type toggles: core, plugins, themes, translations.

## CODING STANDARDS
- **WordPress Coding Standards.** Follow existing patterns; don't hybridize.
- **Indent:** tabs (see `.editorconfig`). YML uses 2 spaces.
- **Files:** `defined('ABSPATH') || die()` guard at top of every PHP include.
- **Naming:** `class-fuerte-wp-*.php` filenames, `Fuerte_Wp_*` classes, `snake_case` methods/functions, `StudlyCaps` classes.
- **Hooks:** registered via the loader (`Fuerte_Wp_Loader`) or `Fuerte_Wp_Hook_Manager::add_hook()`; WordPress hook naming (`fuertewp_<thing>_<action>`).
- **i18n:** text domain `fuerte-wp`, use WP i18n functions.
- **Security:** escape all output (`esc_url`/`esc_html`/`esc_attr`), nonce all forms, capability checks on admin actions.
- **PHPDoc:** required on all public methods.

## WHERE TO LOOK
- **Entry/bootstrap:** `fuerte-wp.php`
- **Orchestration:** `includes/class-fuerte-wp.php`
- **All security rules:** `includes/class-fuerte-wp-enforcer.php`
- **Config logic:** `includes/class-fuerte-wp-config.php`
- **Hook wiring:** `includes/class-fuerte-wp-hook-manager.php`
- **2FA wrapper:** `includes/class-fuerte-wp-two-factor.php`
- **UpdraftCentral guard:** `includes/class-fuerte-wp-updraft-guard.php`
- **Admin UI:** `admin/class-fuerte-wp-admin.php`
- **Tests:** `tests/unit/` (Pest)

## NOTES / GOTCHAS
1. **Never edit `includes/two-factor/`** — regenerated by `scripts/sync-two-factor.sh`. Policy lives in the wrapper.
2. **`vendor/` is committed** (WP host has no Composer). Dev deps must be installed before `composer test`. Production checkouts use `composer production` (`--no-dev`).
3. **Config is transient-cached.** After changing enforcer/config logic, run `delete_transient('fuertewp_config')` in the running site or the old value persists.
4. **`FUERTEWP_DISABLE_2FA` is process-global in tests.** The test that defines it MUST run last in the file.
5. **`tests/bootstrap.php`** hardcodes `vendor/brain/monkey/inc/api.php` — won't exist in `--no-dev` checkouts. Add a `file_exists` guard if tests run from prod.
6. **Bundled Two-Factor lib ≈ standalone plugin** (6 global functions + `Two_Factor_Core`). Loading both = fatal. The `class_exists` guard in `boot()` is load-bearing.
7. **File-config dead toggle:** when `wp-config-fuerte.php` exists, admin checkboxes render but don't save. Affects every field, not just 2FA. Separate refactor.
8. **Mailpit dev mail:** `wp_mail()` under PHP-FPM requires the catchmail wrapper + FPM pool NOT to override `GEM_PATH` to a stale Ruby version. See the docker repo's `php-conf/`.
9. `is_enforced_user()` matches literal role slug `'administrator'`. Renamed custom admin roles are missed on multisite.
10. (Fixed 1.12.0) The `emails.*` toggles now map to real WP core hooks verified against vendored core. The old custom `disable_*_emails` filter names were no-ops; do not resurrect them.

## OTHER CONTEXT FILES
- `CLAUDE.md` → symlink to `AGENTS.md` (this file).
- `GEMINI.md` → exists (check for divergence).
- Repo-level `AGENTS.md` (at docker repo root) covers the Docker/infra side, not this plugin.
- `.codegraph/` index present — use codegraph tools for structural queries.
