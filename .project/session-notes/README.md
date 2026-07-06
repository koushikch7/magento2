# Magento2 Upstream Contribution Session — Working Notes

> **PRIVATE** — This directory lives only on the fork (`koushikch7/magento2`).
> It is **never** pushed to `magento/magento2` upstream.
> All files here are internal session records for resuming work across conversations.

---

## Overview

This fork is used to contribute confirmed upstream bug fixes to `magento/magento2`, targeting the `2.4-develop` branch.

**Contributor identity used for all commits and PRs:**
- Name: `Koushik CH`
- Email: `contact@chkoushik.com`
- GitHub: `koushikch7`
- Accenture email: `koushik.c.h@accenture.com`

**Critical constraint:** Zero traces of AI assistance anywhere — no co-authored-by tags, no AI mentions in commit messages, PR bodies, or review comments.

---

## Active PRs

| PR | Issue | Branch | Status (as of 2026-07-05) |
|----|-------|--------|--------------------------|
| [#40918](https://github.com/magento/magento2/pull/40918) | [#26209](https://github.com/magento/magento2/issues/26209) | `fix/26209-billing-address-overwrites-shipping-totals` | **CLEAN** — Unit/Integration/Static/SVC/DB/Health/FunctionalEE PASS; WebAPI FAIL (pre-existing); rebased 2026-07-05 |
| [#40391](https://github.com/magento/magento2/pull/40391) | [#2703](https://github.com/magento/magento2/issues/2703) | `fix/issue-2703-configurable-product-type` | Unit Tests PASSED; **Integration test fix committed** (2026-07-05); rebased; new CI run in progress |
| [#40392](https://github.com/magento/magento2/pull/40392) | [#40157](https://github.com/magento/magento2/issues/40157) | `fix/issue-40157-curl-methods` | **CLEAN** — Unit/Integration/Static/DB/Health/FunctionalEE PASS; SVC MINOR (expected); clean-rebased 2026-07-05 |
| [#40933](https://github.com/magento/magento2/pull/40933) | [#22883](https://github.com/magento/magento2/issues/22883) | `fix/issue-22883-parallel-deploy-exit-code` | **VERIFIED** — CI pre-existing failures only (no code action needed); fix confirmed on live Magento 2.4.9 Docker 2026-07-06 |

---

## What Each PR Fixes

### PR #40918 — Billing address zeroes out shipping amount
- **Root cause:** `TotalsCollector::collect()` iterates `getAllAddresses()` unconditionally. The billing address (always zero shipping) was processed last, overwriting the shipping totals set by the shipping address.
- **Fix (two-part):**
  1. Wrap the three shipping-setter calls with `if ($address->getAddressType() !== Address::ADDRESS_TYPE_BILLING)`.
  2. Initialize `$total->setShippingAmount(0); $total->setBaseShippingAmount(0);` BEFORE the loop so virtual-only quotes (only billing address) get shipping=0 rather than null.
- **Files:** `app/code/Magento/Quote/Model/Quote/TotalsCollector.php` + new unit tests (two scenarios: normal + virtual-only).

### PR #40391 — Configurable product converted to simple when variants removed
- **Root cause:** `Builder/Plugin.php::afterBuild()` calls `setTypeId(TYPE_SIMPLE)` when `attributes` param is null (not an explicit empty array). This incorrectly converts configurable products when no attribute action was intended.
- **Fix (two places):**
  1. `Builder/Plugin.php` — Changed to `is_array($attributes)` guard: `null` = no action (preserve type); `[]` = explicit deletion (convert to simple); `[1,2,...]` = set configurable.
  2. `TypeTransitionManager/Plugin/Configurable.php` — Added early return: if product is already `configurable`, skip `$proceed()` (defensive; TypeTransitionManager never touches configurable anyway).
- **Files:** Two plugin files + unit tests for each + new integration test.

### PR #40392 — Curl client missing HTTP methods
- **Root cause:** `Magento\Framework\HTTP\Client\Curl` implements `ClientInterface` but only had `get()` and `post()`. PUT, DELETE, PATCH, OPTIONS, HEAD, TRACE, CONNECT were absent.
- **Fix:** Added 7 new public methods, all delegating to `makeRequest()`.
- **Side effect:** All 7 new methods on an `@api`-annotated class trigger V015 MINOR SVC violations — expected, informational, reviewed separately by maintainers.
- **Files:** `lib/internal/Magento/Framework/HTTP/Client/Curl.php` + updated test helper + new unit test.

---

## Directory Contents

| File | Purpose |
|------|---------|
| `README.md` | This file — quick orientation |
| `CHANGELOG.md` | Chronological log of all changes across sessions |
| `RUNBOOK.md` | Pin-to-pin operational guide for resuming work |

---

## Key Commands

```bash
# Check PR status
gh pr view 40391 --repo magento/magento2 --json statusCheckRollup

# Trigger test re-run (comment on PR)
gh pr comment 40391 --repo magento/magento2 --body "@magento run all tests"
gh pr comment 40391 --repo magento/magento2 --body "@magento run Unit Tests"

# Push to fork only (never push to upstream)
git push origin <branch-name>

# Get check run report URLs
gh api "repos/magento/magento2/commits/<sha>/check-runs" \
  --jq '.check_runs[] | {name: .name, conclusion: .conclusion, status: .status, output_summary: .output.summary}'
```

---

## Important Notes

- Magento CI reports are hosted at `public-results-storage-prod.magento-testing-service.engineering` and expire after ~24 hours.
- Always retrieve report URLs from the GitHub check-runs API (`/check-runs` endpoint), not from the PR status checks endpoint, as the former includes the full `output.summary` with direct links.
- PHPUnit 12 (used in Magento CI) removed `addMethods()` and rejects configuring magic methods on `DataObject` subclasses. Use real instances or anonymous class stubs instead.
- PRs auto-close if no activity for ~2 weeks. Keep at least one comment per 10 days.
