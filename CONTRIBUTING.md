# Contributing to Laravel RatingKit 🤝

Thank you for helping improve Laravel RatingKit.

## 🧰 Development Setup

```bash
git clone https://github.com/EloquentWorks/RatingKit.git
cd RatingKit
composer install
composer quality
```

## 🌿 Branches

Create a focused branch:

```bash
git checkout -b feature/algorithm-name
git checkout -b fix/team-rating-rounding
```

## ✅ Pull Request Requirements

- Add or update tests
- Preserve backward compatibility unless the change is documented as breaking
- Update the relevant documentation
- Run `composer quality`
- Keep algorithm math isolated from Eloquent persistence
- Cite authoritative algorithm references in the pull request when adding mathematical behavior

## 🧮 New Algorithms

New drivers should:

- Implement `RatingAlgorithm`
- Return one `RatingChange` for every competitor
- Support deterministic tests
- Document team and multi-team behavior
- Explain configurable parameters
- Avoid trademark or certification claims

## 🔐 Security

Do not open public issues for security vulnerabilities. Follow [SECURITY.md](SECURITY.md).
