# Filament SiteKit

Free analytics package for Filament.

## Features

- ✓ Google Analytics 4
- ✓ One-click tracking install
- ✓ One-click setup

## Compatibility

- Laravel 10/11/12
- Filament 3/4/5

## Installation

```bash
composer require boxincoded/filament-sitekit
php artisan sitekit:install
php artisan migrate
```

## Setup

Open Setup Wizard:

`/admin/sitekit/setup`

Google OAuth redirect URL:

`/admin/sitekit/oauth/google/callback`

Environment example:

```env
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=https://your-domain.com/admin/sitekit/oauth/google/callback
```

## Test Install

```bash
composer require boxincoded/filament-sitekit
php artisan sitekit:install
php artisan migrate
```

## Security

- Access and refresh tokens are encrypted using Laravel `Crypt::encryptString` / `Crypt::decryptString`.
- Tokens are never intentionally written to logs.

## Config Reference

`config/filament-sitekit.php` includes:

- `google.client_id`
- `google.client_secret`
- `google.redirect_uri`
- `google.scopes`
- `cache.ttl_seconds`
- `sync.enabled`
- `sync.schedule`
- `sync.auto_schedule`
- `sync.queue`
- `workspace.resolver`
- `tracking.enabled`
- `tracking.method`
- `tracking.inject_only_if_missing`
- `tracking.exclude_paths`
- `filament.admin_path_prefix`
- `encryption.key_usage`
- `connectors.enabled.ga4`
- `authorization.gate`

## Versioning

This package follows **Semantic Versioning** (`MAJOR.MINOR.PATCH`).

- `PATCH` = bug fixes and safe internal improvements
- `MINOR` = backwards-compatible features
- `MAJOR` = breaking API or behavior changes

Version source of truth is `BoxinCode\FilamentSiteKit\FilamentSiteKit::VERSION`.

Release checklist:

1. Update `FilamentSiteKit::VERSION`
2. Add release notes in `CHANGELOG.md`
3. Commit and push to `main`
4. Create git tag (example):

```bash
git tag v1.0.0
git push origin v1.0.0
```

5. Publish GitHub release for the tag

## License

MIT
