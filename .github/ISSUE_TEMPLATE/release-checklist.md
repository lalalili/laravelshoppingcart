---
name: Release Checklist
about: Checklist for publishing lalalili/laravelshoppingcart releases
title: 'release: vX.Y.Z'
labels: ['release']
assignees: []
---

## Release checklist

- [ ] Confirm target tag/version (`vX.Y.Z`) and changelog highlights.
- [ ] Run package tests (`composer test`).
- [ ] Run package static analysis (`composer analyse`).
- [ ] Verify `README.md` reflects current API/breaking changes.
- [ ] Push tag and confirm `Release` workflow passed.
- [ ] Verify Packagist has indexed the new tag.
- [ ] Install smoke check: `composer require lalalili/laravelshoppingcart:^X.Y` in a clean Laravel app.
