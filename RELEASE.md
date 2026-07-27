# 🚀 Releasing Laravel RatingKit

Laravel RatingKit follows Semantic Versioning.

## 🧭 Prepare

1. Complete [the release checklist](docs/release-checklist.md).
2. Prepare the GitHub release notes.
3. Confirm the working tree is clean.

## ✅ Validate

```bash
composer validate --strict
composer quality
```

Test the production dependency set:

```bash
composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader
```

Smoke-test supported Laravel versions in clean applications.

## 💾 Commit

```bash
git add .
git commit -m "Prepare v1.0.0 release"
git push
```

## 🏷️ Tag

```bash
git tag -a v1.0.0 -m "Laravel RatingKit v1.0.0"
git push origin v1.0.0
```

Never move a published stable tag. Create a new version when a released tag needs correction.

## 📣 GitHub Release

Create a release from `v1.0.0` and use `RELEASE_NOTES_v1.0.0.md`.

Suggested title:

```text
v1.0.0 — Initial Release
```

## 📦 Package Registry

Confirm the package registry receives the tag and resolves:

```bash
composer show eloquent-works/rating-kit --all
```

## 🧪 Post-Release Smoke Test

```bash
composer create-project laravel/laravel rating-kit-smoke
cd rating-kit-smoke
composer require eloquent-works/rating-kit:^1.0
php artisan rating-kit:install --migrate --views
```

Verify starting a masquerade session, stopping it, session restoration, audit logging, duration expiration, route blocking, and the Blade banner.
