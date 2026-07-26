# ⬆️ Upgrade Guide

## 🧰 Standard Upgrade

```bash
composer update eloquent-works/rating-kit
php artisan vendor:publish --tag=rating-kit-migrations
php artisan migrate
php artisan optimize:clear
```

## ✅ Quality Checks

```bash
composer validate --strict
composer audit
composer quality
```

## 🔎 Review Before Upgrading

- New migrations
- New configuration keys
- Algorithm option defaults
- Supported PHP and Laravel versions
- Release notes
- Custom algorithm compatibility

## 🧮 Rating Stability

Changing an algorithm's parameters changes future updates but does not automatically recalculate historical ratings. Use a new pool name when you need to preserve the old ladder and start a new calculation policy.
