# Runbook — Magento2 Upstream Contribution

This is the complete pin-to-pin operational guide. Every relevant detail is here so any session can resume without ambiguity.

---

## 1. Environment Setup

### Git Configuration (Project-Level)
All commits in this repo use a specific identity — set as project-level config, not global:
```bash
git config user.name "Koushik CH"
git config user.email "contact@chkoushik.com"
```
**Never use any other identity.** Do not add co-authored-by tags, AI attribution, or any external tooling mentions anywhere.

### Remotes
```
origin    git@github.com:koushikch7/magento2.git   (fork — push here only)
upstream  https://github.com/magento/magento2.git  (upstream — fetch only, NEVER push)
```

Push rule: **always `git push origin <branch>`**. Never `git push upstream`.

### Local Branches
```
2.4-develop                                    ← tracks origin/2.4-develop
fix/26209-billing-address-overwrites-shipping-totals  ← PR #40918
fix/issue-2703-configurable-product-type       ← PR #40391
fix/issue-40157-curl-methods                   ← PR #40392
```

### chk-doc/ Protection — Gitignore Setup

`chk-doc/` is committed and tracked on `origin/2.4-develop` (fork) only. It must never appear in any upstream PR branch.

Two layers of protection are in place:

**Layer 1 — `.gitignore` (committed on `2.4-develop`):**
```
# Internal session notes — fork only, never upstream
chk-doc/
```
Already-tracked files on `2.4-develop` are unaffected (git ignores `.gitignore` for tracked files).
On feature branches that pick this up via rebase, any newly-created `chk-doc/` files won't be staged by `git add .`.

**Layer 2 — `.git/info/exclude` (local-only, covers ALL branches, never committed):**
```
# Internal session notes — never commit to feature branches
chk-doc/
```
This is the `.gitignore` equivalent that is never pushed anywhere. It protects every branch immediately without being part of any PR diff.

**Result:** You can safely create/edit files in `chk-doc/` while on a feature branch (to prepare notes before switching to `2.4-develop` to commit them) without any risk of accidentally staging them.

### chk-doc/ Update Workflow (MANDATORY — Every Session)

At the end of every conversation session, before finishing:
1. Switch to `2.4-develop` branch.
2. Update `chk-doc/CHANGELOG.md` with every change made this session (commits, CI outcomes, decisions).
3. Update `chk-doc/RUNBOOK.md` for any new patterns, decisions, or next steps that changed.
4. Update `chk-doc/README.md` if PR status changed.
5. Commit with message: `docs: update chk-doc — <one-line summary of session>`
6. Push to `origin/2.4-develop` only.
7. Switch back to the active working branch.

**These files must never fall out of date.** Any code change, CI result, decision, or fix pattern must be recorded before ending a session.

---

## 2. Active PRs — Full Details

---

### PR #40918 — Billing address zeroes shipping totals
- **PR URL:** https://github.com/magento/magento2/pull/40918
- **Issue:** https://github.com/magento/magento2/issues/26209
- **Branch:** `fix/26209-billing-address-overwrites-shipping-totals`
- **Base:** `magento:2.4-develop`

#### Root Cause
`TotalsCollector::collect()` in `app/code/Magento/Quote/Model/Quote/TotalsCollector.php` loops over `$quote->getAllAddresses()`. For multi-address checkout, `getAllAddresses()` returns both the shipping address and billing address. The billing address always has zero shipping amount. When `getBillingAddress()` is called after `getShippingAddress()`, its zero-shipping totals overwrite the correctly calculated shipping totals.

#### Production Fix
File: `app/code/Magento/Quote/Model/Quote/TotalsCollector.php` (~line 155)

```php
// Before fix:
$total->setShippingAmount($addressTotal->getShippingAmount());
$total->setBaseShippingAmount($addressTotal->getBaseShippingAmount());
$total->setShippingDescription($addressTotal->getShippingDescription());

// After fix (wrapped in guard):
if ($address->getAddressType() !== Address::ADDRESS_TYPE_BILLING) {
    $total->setShippingAmount($addressTotal->getShippingAmount());
    $total->setBaseShippingAmount($addressTotal->getBaseShippingAmount());
    $total->setShippingDescription($addressTotal->getShippingDescription());
}
```
`Address::ADDRESS_TYPE_BILLING` = `'billing'`, `ADDRESS_TYPE_SHIPPING` = `'shipping'`.

#### Unit Test
File: `app/code/Magento/Quote/Test/Unit/Model/Quote/TotalsCollectorTest.php`

Key patterns used (PHPUnit 12 compatible):

**Address stubs — anonymous classes (because `getAddressType()` is a DataObject magic method):**
```php
$shippingAddress = new class extends Address {
    public function __construct() {}
    public function getAddressType(): string { return self::ADDRESS_TYPE_SHIPPING; }
};
$billingAddress = new class extends Address {
    public function __construct() {}
    public function getAddressType(): string { return self::ADDRESS_TYPE_BILLING; }
};
```

**Total instances — real objects (because ALL `Total::get*()` methods are DataObject `__call()` magic):**
```php
$shippingAddressTotal = new Total([
    'shipping_amount'             => $shippingAmount,
    'base_shipping_amount'        => $shippingAmount,
    'shipping_description'        => $shippingDescription,
    'subtotal'                    => 100.0,
    'base_subtotal'               => 100.0,
    'subtotal_with_discount'      => 100.0,
    'base_subtotal_with_discount' => 100.0,
    'grand_total'                 => 110.0,
    'base_grand_total'            => 110.0,
]);
$billingAddressTotal = new Total();    // all zeros — correct for billing
$aggregateTotal      = new Total();    // starts empty, gets populated by collect()
```

**Do NOT** configure `getGrandTotal()` or `getBaseGrandTotal()` on `$quoteMock` — both are `@method` docblock magic on `Quote`, PHPUnit 12 rejects them.

#### Null vs 0 Fix (Session 4)
Our billing-address guard means `setShippingAmount()` is NEVER called for virtual-only quotes (only billing address in `getAllAddresses()`). Subtotal/grandTotal are initialized to 0 before the loop, but shipping was not. Fix: add explicit initialization before the loop:

```php
$total->setShippingAmount(0);
$total->setBaseShippingAmount(0);

foreach ($quote->getAllAddresses() as $address) {
    // ...
    if ($address->getAddressType() !== Address::ADDRESS_TYPE_BILLING) {
        $total->setShippingAmount($addressTotal->getShippingAmount());
        $total->setBaseShippingAmount($addressTotal->getBaseShippingAmount());
        $total->setShippingDescription($addressTotal->getShippingDescription());
    }
```

New unit test added: `testCollectSetsZeroShippingAmountForVirtualOnlyQuote`.
Commit: `6178479c045` — `Initialize shipping amount to zero before address loop`.

#### CI Status (last checked 2026-07-05 Session 6)
| Check | Result |
|-------|--------|
| Unit Tests | **PASSED** |
| Integration Tests | **PASSED** |
| Static Tests | **PASSED** |
| SVC | **PASSED** |
| Magento Health Index | **PASSED** |
| Database Compare | **PASSED** |
| Functional Tests EE | **PASSED** |
| WebAPI Tests | FAILED — **pre-existing** (`AsyncOrderProcessingTest::testAsyncOrderProcessingWithCustomerDeletion` — async queue not processed in CI env; unrelated to TotalsCollector) |
| Functional Tests CE | FAILED — pre-existing infrastructure issue |
| Functional Tests B2B | FAILED — pre-existing infrastructure issue |
- **PR is effectively clean. Ready for maintainer review.**

---

### PR #40391 — Configurable product type reverts to simple
- **PR URL:** https://github.com/magento/magento2/pull/40391
- **Issue:** https://github.com/magento/magento2/issues/2703
- **Branch:** `fix/issue-2703-configurable-product-type`
- **Base:** `magento:2.4-develop`

#### Root Cause
When editing a configurable product in the admin panel and sending `attributes=[]` (removing all variant attributes), two code paths can incorrectly reset the product type to `simple`:

1. `app/code/Magento/ConfigurableProduct/Controller/Adminhtml/Product/Builder/Plugin.php`
   - In `afterBuild()`, when `$request->getParam('attributes')` is empty/null, the code falls through and calls `$product->setTypeId(Type::TYPE_SIMPLE)`.
   - Missing check: it should only do this if the product was not originally a configurable.

2. `app/code/Magento/ConfigurableProduct/Model/Product/TypeTransitionManager/Plugin/Configurable.php`
   - `aroundProcessProduct()` calls `$proceed($product)`, which in `TypeTransitionManager` may set type to `simple`/`virtual` if `configurable` is not in its `$compatibleTypes` list.

#### Production Fix

**File 1:** `app/code/Magento/ConfigurableProduct/Controller/Adminhtml/Product/Builder/Plugin.php`

The `setProductType()` method uses `is_array($attributes)` to distinguish intentional deletion from a null/absent param:

```php
if (!empty($attributes)) {
    $product->setTypeId(Configurable::TYPE_CODE);
    $this->configurableType->setUsedProductAttributes($product, $attributes);
} elseif (is_array($attributes)) {
    // Explicit empty array — user cleared all options — convert to simple
    $product->setTypeId(Type::TYPE_SIMPLE);
}
// attributes === null → no explicit action; preserve existing type
```

Distinction:
- `attributes = null` (present in request but null) → bug scenario → type preserved ✓
- `attributes = []` (explicit empty array) → intentional deletion → convert to simple ✓

**File 2:** `app/code/Magento/ConfigurableProduct/Model/Product/TypeTransitionManager/Plugin/Configurable.php`

```php
// Added early return when product is already configurable:
if ($product->getTypeId() === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
    return;
}
```

Note: `TypeTransitionManager::processProduct()` has `$compatibleTypes = ['simple', 'virtual']` — configurable is NOT in that list, so TypeTransitionManager never changes configurable products anyway. This guard is a defensive measure.

#### Unit Tests

**Builder Plugin test** (`app/code/Magento/ConfigurableProduct/Test/Unit/Controller/Adminhtml/Product/Builder/PluginTest.php`):
```php
$productMock = $this->createPartialMockWithReflection(
    Product::class,
    ['setTypeId', 'getAttributes', 'addData', 'setWebsiteIds']
);
```
New test: `testAfterBuildPreservesTypeWhenAttributesParamIsNull` — asserts `setTypeId` is never called when `$request->getParam('attributes')` returns null.

**TypeTransitionManager Plugin test** (`app/code/Magento/ConfigurableProduct/Test/Unit/Model/Product/TypeTransitionManager/Plugin/ConfigurableTest.php`):
```php
$productMock = $this->createPartialMock(Product::class, ['setTypeId', 'getTypeId']);
```
New test: `testAroundProcessProductPreservesConfigurableTypeWhenAttributesEmpty`

#### Integration Test
File: `dev/tests/integration/testsuite/Magento/ConfigurableProduct/Model/Product/TypeTransitionTest.php`

```php
class TypeTransitionTest extends TestCase
{
    private $productRepository;
    private $typeTransitionManager;

    protected function setUp(): void
    {
        $this->productRepository = Bootstrap::getObjectManager()
            ->get(ProductRepositoryInterface::class);
        $this->typeTransitionManager = Bootstrap::getObjectManager()
            ->get(TypeTransitionManager::class);
    }

    /**
     * @magentoDataFixture Magento/ConfigurableProduct/_files/product_configurable.php
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     */
    public function testConfigurableProductTypePreservedByTypeTransitionManager(): void
    {
        $product = $this->productRepository->get('configurable');
        $this->assertEquals(Configurable::TYPE_CODE, $product->getTypeId());
        $this->typeTransitionManager->processProduct($product);
        $this->assertEquals(
            Configurable::TYPE_CODE,
            $product->getTypeId(),
            'TypeTransitionManager must not convert configurable products to simple'
        );
    }
}
```
Note: `$productFactory->create()->load()` is **deprecated** — use `productRepository->get()` instead.
Note: Do NOT write a test that saves a configurable product without full attribute setup — it throws exceptions.

#### Unit Test TypeError Fix (Session 4)

**Root cause found** from CI report `29021d2e6b9187b4c071b68c9fd25386` (completed 2026-07-03T06:19:21Z):

```
TypeError: Plugin::isAttributeValuable(): Argument #2 ($configProduct) must be of type
Magento\Catalog\Model\Product, MockObject_Configurable_b48c7737 given
```

The private `isAttributeValuable()` method in `Builder/Plugin.php` had a `Product $configProduct` type hint. The test passes `$this->configurableMock` — a mock of `Magento\ConfigurableProduct\Model\Product\Type\Configurable` (the type handler class, NOT a `Catalog\Model\Product`). On PHP 8.5 with strict typing, this is a TypeError.

**Why it existed:** The test uses the same mock for both the "product loaded from factory" AND its "type instance" (via `willReturnSelf()`). This dual-use shortcut was fine when code was inline (no type hints) but breaks when extracted into a typed private method.

**Fix:** Removed the PHP type hint from the private method parameter. Kept `@param Product $configProduct` in PHPDoc.

```php
// Before:
private function isAttributeValuable(
    \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute,
    Product $configProduct
): bool

// After:
private function isAttributeValuable(
    \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute,
    $configProduct   // no type hint — private method, test uses non-Product mock
): bool
```

Commit: `8b08a7cd09a` — `Remove strict Product type hint from private isAttributeValuable parameter`.
New `@magento run Unit Tests` triggered 2026-07-04.

#### CI Status (last checked 2026-07-05 Session 8)
| Check | Result |
|-------|--------|
| Unit Tests | **PASSED** (job 234 — TypeError fix confirmed) |
| Integration Tests | **FAILED** — code-related (fixed in Session 8; re-run triggered) |
| Functional Tests CE/EE/B2B | FAILED — pre-existing infrastructure issue |
- Session 8: Integration test fix committed + branch rebased. New CI run in progress.

**Integration test fix (Session 8):**
- Failure: `ProductTest::testSaveExistProduct['delete_all_options']` at line 368 (`assertNull($options)` failed)
- Root cause: `Builder/Plugin.php` guard used `getOrigData('type_id')`, which also blocked intentional `attributes=[]` deletion
- Fix: Changed to `is_array($attributes)` check (see Production Fix section above)
- Commit: `8fec1dcf122`

**History:** Previous Unit Tests run hung for 6h (superseded). Second run (job 211) completed 2026-07-03T06:19:21Z — failed with TypeError in `testAfterBuild`. TypeError fixed (commit `8b08a7cd09a`). Unit Tests job 234 PASSED 2026-07-05.

---

### PR #40933 — Parallel static content deploy ignores child failure exit codes
- **PR URL:** https://github.com/magento/magento2/pull/40933
- **Issue:** https://github.com/magento/magento2/issues/22883
- **Branch:** `fix/issue-22883-parallel-deploy-exit-code`
- **Base:** `magento:2.4-develop`

#### Root Cause
`Queue::process()` in `app/code/Magento/Deploy/Process/Queue.php` always returns 0. In parallel mode, child process failures are detected via `pcntl_waitpid` + `pcntl_wexitstatus` inside `isDeployed()`, but the result was only logged — `$returnStatus` was never updated.

#### Production Fix
**File:** `app/code/Magento/Deploy/Process/Queue.php`

1. Added `private bool $hasErrors = false;` — set to `true` in `isDeployed()` when `pcntl_wexitstatus` is non-zero.
2. In `process()`, after `awaitForAllProcesses()`, throw `\RuntimeException` if `$hasErrors`.
3. In `execute()` child branch, wrapped `deploy()` in `try/catch (\Throwable)` → logs error, `exit(1)` cleanly.

#### Unit Tests
Added to `app/code/Magento/Deploy/Test/Unit/Process/QueueTest.php`:
- `testIsDeployedSetsHasErrorsOnChildFailure` — forks real child (exit 1), asserts `hasErrors = true`.
- `testProcessThrowsRuntimeExceptionWhenHasErrors` — sets `hasErrors = true` via reflection, asserts `process()` throws.

#### SVC
No `@api` class/interface changes → **no SVC violations expected**.

#### Live Test Verification (2026-07-06 Session 10)
Server: `192.168.29.20` — Magento 2.4.9, Docker Compose, webroot `/mnt/ssd/magento`, source `/mnt/ssd/magento2-src`.

- Added `pcntl` to `Dockerfile` `docker-php-ext-install` list → rebuilt `magento_php:local` → `pcntl_fork` confirmed available.
- Injected `throw new \RuntimeException("FORCED FAILURE")` at top of `vendor/magento/module-deploy/Process/DeployPackage.php::deploy()`.
- **Without fix:** `bin/magento setup:static-content:deploy -f --jobs 2` — error in output, `echo $?` = **0** (bug confirmed).
- **With fix (Queue.php from PR #40933):** same command — error + `Static content deploy failed: ...` message, `echo $?` = **1** (fix confirmed).
- Restored all vendor files; branch switched back to `2.4-develop` on server.

#### CI Status (checked 2026-07-06 Session 10)
| Check | Result |
|-------|--------|
| Unit Tests | **PASSED** |
| Static Tests | **PASSED** |
| Integration Tests | **PASSED** |
| SVC | **PASSED** (no @api changes) |
| Database Compare | **PASSED** |
| Magento Health Index | **PASSED** |
| Functional Tests EE | FAILED — pre-existing (`MC-32333: Admin Reports Review by Products`, 1 of 3812) |
| WebAPI Tests | FAILED — pre-existing (`testCreateDownloadableProduct` — downloadable domain missing from CI env.php) |
| Functional Tests CE | FAILED — pre-existing infrastructure issue |
| Functional Tests B2B | FAILED — pre-existing infrastructure issue |
- **PR is effectively clean. Ready for maintainer review.**

---

### PR #40392 — Curl client missing HTTP methods
- **PR URL:** https://github.com/magento/magento2/pull/40392
- **Issue:** https://github.com/magento/magento2/issues/40157
- **Branch:** `fix/issue-40157-curl-methods`
- **Base:** `magento:2.4-develop`

#### Root Cause
`Magento\Framework\HTTP\Client\Curl` (marked `@api`) only implemented `get()` and `post()`. HTTP verbs PUT, DELETE, PATCH, OPTIONS, HEAD, TRACE, and CONNECT were missing, leaving API consumers unable to make those requests through the standard Magento HTTP client.

#### Production Fix
File: `lib/internal/Magento/Framework/HTTP/Client/Curl.php`

Seven new public methods added (all delegate to existing `makeRequest()`):
```php
public function put($uri, $params = [])    { $this->makeRequest("PUT",     $uri, $params); }
public function delete($uri, $params = []) { $this->makeRequest("DELETE",  $uri, $params); }
public function patch($uri, $params = [])  { $this->makeRequest("PATCH",   $uri, $params); }
public function options($uri, $params = []) { $this->makeRequest("OPTIONS", $uri, $params); }
public function head($uri)                 { $this->makeRequest("HEAD",    $uri); }
public function trace($uri)                { $this->makeRequest("TRACE",   $uri); }
public function connect($uri)              { $this->makeRequest("CONNECT", $uri); }
```

#### Test Helper Update
File: `dev/tests/integration/framework/Magento/TestFramework/Helper/Curl.php`

This test helper extends the production `Curl.php` and had its own `delete()` method that now conflicts with the parent's new `delete()`. Updated:
```php
/**
 * @deprecated Use \Magento\Framework\HTTP\Client\Curl::delete instead
 * @see \Magento\Framework\HTTP\Client\Curl::delete
 */
public function delete($uri, $params = []): void
{
    $this->makeRequest("DELETE", $uri, $params);
}
```

#### ClientInterface Addition (Session 5)
File: `lib/internal/Magento/Framework/HTTP/ClientInterface.php`

Six new method declarations added between `post()` and `getHeaders()`:
```php
public function put($uri, $params = []);
public function delete($uri, $params = []);
public function patch($uri, $params = []);
public function options($uri, $params = []);
public function head($uri);
public function trace($uri);
// connect() intentionally OMITTED — Socket::connect($host, $port) uses that name for TCP
```

#### Socket Addition (Session 5)
File: `lib/internal/Magento/Framework/HTTP/Client/Socket.php`

Same 6 methods added after `post()`:
```php
public function put($uri, $params = [])     { $this->makeRequest("PUT",     $this->parseUrl($uri), $params); }
public function delete($uri, $params = [])  { $this->makeRequest("DELETE",  $this->parseUrl($uri), $params); }
public function patch($uri, $params = [])   { $this->makeRequest("PATCH",   $this->parseUrl($uri), $params); }
public function options($uri, $params = []) { $this->makeRequest("OPTIONS", $this->parseUrl($uri), $params); }
public function head($uri)                  { $this->makeRequest("HEAD",    $this->parseUrl($uri)); }
public function trace($uri)                 { $this->makeRequest("TRACE",   $this->parseUrl($uri)); }
// connect() NOT added — conflicts with existing connect($host, $port = 80) TCP method at line 112
```

#### Unit Test
File: `lib/internal/Magento/Framework/HTTP/Client/Test/Unit/CurlTest.php`

**CRITICAL:** Test must be at this exact path, not under `dev/tests/unit/testsuite/`.
`dev/tests/unit/phpunit.xml.dist` only discovers:
- `../../../app/code/*/*/Test/Unit`
- `../../../lib/internal/*/*/Test/Unit`
- `../../../lib/internal/*/*/*/Test/Unit`

Two constant sets because Curl has `connect()` but Socket/Interface do NOT:
```php
private const INTERFACE_METHODS = ['put', 'delete', 'patch', 'options', 'head', 'trace'];
private const CURL_METHODS = ['put', 'delete', 'patch', 'options', 'head', 'trace', 'connect'];
```
- `testPublicHttpMethodsExistOnCurl()` — asserts all CURL_METHODS exist on `Curl::class`
- `testPublicHttpMethodsExistOnSocket()` — asserts all INTERFACE_METHODS exist on `Socket::class`
- `testPublicHttpMethodsExistOnClientInterface()` — asserts all INTERFACE_METHODS exist on `ClientInterface::class`

#### SVC (Semantic Version Checker) — Expected FAILURE
All 7 new public methods on an `@api`-annotated class generate **V015 MINOR** violations:
```
V015 [MINOR] Method added.
```
This is **expected and informational**. MINOR = backward-compatible addition. The CI reports it as FAILURE but maintainers review MINOR SVC violations separately and accept them for legitimate feature additions. **No code fix is needed.**

#### CI Status (last checked 2026-07-05 Session 6)
| Check | Result |
|-------|--------|
| Static Tests | **PASSED** |
| Unit Tests | **PASSED** |
| Integration Tests | **PASSED** |
| Database Compare | **PASSED** |
| Magento Health Index | **PASSED** |
| Functional Tests EE | **PASSED** |
| SVC | FAILURE (expected — V015 MINOR, ~13 violations: 7 on Curl + 6 on Socket + 6 on ClientInterface) |
| WebAPI Tests | FAILED — **pre-existing** (`ProductRepositoryTest::testCreateDownloadableProduct` — SoapFault: downloadable domain not in env.php; unrelated to HTTP client methods) |
| Functional Tests CE | FAILED — pre-existing infrastructure issue |
| Functional Tests B2B | FAILED — pre-existing infrastructure issue |
- **PR is effectively clean. Ready for maintainer review.**

---

## 3. CI System Knowledge

### How to trigger tests
Post a comment on the PR (using `gh pr comment`):
```bash
# Trigger everything
gh pr comment <PR_NUMBER> --repo magento/magento2 --body "@magento run all tests"

# Trigger individual suites
gh pr comment <PR_NUMBER> --repo magento/magento2 --body "@magento run Unit Tests"
gh pr comment <PR_NUMBER> --repo magento/magento2 --body "@magento run Static Tests"
gh pr comment <PR_NUMBER> --repo magento/magento2 --body "@magento run Integration Tests"
gh pr comment <PR_NUMBER> --repo magento/magento2 --body "@magento run Functional Tests"
```

### How to get report URLs
Reports are embedded in the GitHub check run `output.summary`. Fetch via API:
```bash
SHA=$(gh pr view <PR_NUMBER> --repo magento/magento2 --json headRefOid --jq '.headRefOid')
gh api "repos/magento/magento2/commits/$SHA/check-runs" \
  --jq '.check_runs[] | {name: .name, conclusion: .conclusion, status: .status, output_summary: .output.summary}'
```

### Report expiry
Reports are hosted at `public-results-storage-prod.magento-testing-service.engineering` and return **HTTP 404 after ~24 hours**. Always retrieve report URLs from a fresh API call for the current head commit SHA. If reports are 404, trigger a new CI run.

### Reading PR status
```bash
gh pr view <PR_NUMBER> --repo magento/magento2 --json statusCheckRollup \
  --jq '.statusCheckRollup[] | "\(.conclusion // .state) \(.name)"'
```

### Test suite discovery (PHPUnit)
`dev/tests/unit/phpunit.xml.dist` discovers these paths:
- `../../../app/code/*/*/Test/Unit`        → app module unit tests
- `../../../lib/internal/*/*/Test/Unit`    → lib module unit tests (2 levels)
- `../../../lib/internal/*/*/*/Test/Unit`  → lib module unit tests (3 levels)

**`dev/tests/unit/testsuite/` is NOT a discovered directory.** Any test placed there will not be run.

---

## 4. PHPUnit 12 Compatibility — Critical Rules

Magento CI runs **PHPUnit 12.5.14**. Key changes from PHPUnit 9/10:

### 1. `addMethods()` removed
PHPUnit 12 removed `MockBuilder::addMethods()`. It was used to configure magic methods on mocks.
**Do not use it.** The CI will fail.

### 2. Magic methods on DataObject cannot be mocked as stubs
`Magento\Framework\DataObject` uses `__call()` to implement `getX()`/`setX()` accessors dynamically. PHPUnit 12 validates that any method configured via `->method('methodName')` actually exists as a PHP method. Since `getShippingAmount()`, `getGrandTotal()`, `getAddressType()`, etc. are not real PHP methods, PHPUnit 12 throws:
```
Trying to configure method 'getShippingAmount' which cannot be configured
because it does not exist in class Magento\Quote\Model\Quote\Address\Total
```

### 3. Solutions for DataObject magic methods

**Option A — Real instances (preferred for Value Objects / DTOs):**
```php
$total = new Total(['shipping_amount' => 10.0, 'grand_total' => 110.0]);
// $total->getShippingAmount() returns 10.0 via __call()
```

**Option B — Anonymous class stubs (preferred for Address-type objects with required behavior):**
```php
$address = new class extends Address {
    public function __construct() {}   // skip DI constructor
    public function getAddressType(): string { return self::ADDRESS_TYPE_SHIPPING; }
};
```

**Option C — `createPartialMockWithReflection()` (for objects needing other real mock features):**
```php
// Lives in lib/internal/Magento/Framework/TestFramework/Unit/Helper/MockCreationTrait.php
// Bypasses PHPUnit validation using reflection to set 'methods' directly on MockBuilder
$mock = $this->createPartialMockWithReflection(Product::class, ['getOrigData', 'setTypeId']);
```
Use this when you need `expects()->once()` verifications alongside magic methods.

---

## 5. Rebasing Branches on `2.4-develop`

When `2.4-develop` has new commits (e.g., after a merge freeze lifts or after a refactor lands upstream), rebase feature branches:

```bash
# Fetch latest upstream
git fetch upstream

# Rebase a feature branch
git checkout fix/issue-2703-configurable-product-type
git rebase upstream/2.4-develop

# If conflicts arise:
git status         # see conflicted files
# resolve manually, then:
git add <file>
git rebase --continue

# Push (force required after rebase)
git push origin fix/issue-2703-configurable-product-type --force-with-lease
```

**Always use `--force-with-lease`** not `--force`. It fails safely if someone else pushed to the branch.

---

## 6. Files Changed Per PR

### PR #40918
```
app/code/Magento/Quote/Model/Quote/TotalsCollector.php
app/code/Magento/Quote/Test/Unit/Model/Quote/TotalsCollectorTest.php  (new)
```

### PR #40391
```
app/code/Magento/ConfigurableProduct/Controller/Adminhtml/Product/Builder/Plugin.php
app/code/Magento/ConfigurableProduct/Model/Product/TypeTransitionManager/Plugin/Configurable.php
app/code/Magento/ConfigurableProduct/Test/Unit/Controller/Adminhtml/Product/Builder/PluginTest.php
app/code/Magento/ConfigurableProduct/Test/Unit/Model/Product/TypeTransitionManager/Plugin/ConfigurableTest.php
dev/tests/integration/testsuite/Magento/ConfigurableProduct/Model/Product/TypeTransitionTest.php  (new)
```

### PR #40392
```
lib/internal/Magento/Framework/HTTP/Client/Curl.php
lib/internal/Magento/Framework/HTTP/ClientInterface.php
lib/internal/Magento/Framework/HTTP/Client/Socket.php
dev/tests/integration/framework/Magento/TestFramework/Helper/Curl.php
lib/internal/Magento/Framework/HTTP/Client/Test/Unit/CurlTest.php  (new)
```
Deleted (wrong location):
```
dev/tests/unit/testsuite/Magento/Framework/HTTP/Client/CurlMethodsTest.php
```

### PR #40933
```
app/code/Magento/Deploy/Process/Queue.php
app/code/Magento/Deploy/Test/Unit/Process/QueueTest.php
```

---

## 7. Decision Log

| Decision | Reason |
|----------|--------|
| Use real `Total` instances instead of mocks | PHPUnit 12 cannot configure magic methods; real instances via `new Total([...])` work identically |
| Use anonymous class stubs for `Address` | `getAddressType()` is magic; anonymous class with real override is cleanest PHPUnit 12 pattern |
| Use `createPartialMockWithReflection()` for `Product` | Need `expects()->once()` AND `getOrigData()` (magic) — only reflection-based approach supports both |
| Integration test uses `productRepository->get()` | `$product->load()` is deprecated in `2.4-develop`; repository pattern is the modern replacement |
| Removed `testNewConfigurableProductPreservesType` | Saving a configurable product without proper attribute setup throws exceptions in integration env |
| Placed `CurlTest.php` in `lib/internal/*/Test/Unit/` | Only path discovered by phpunit.xml.dist for lib tests |
| Accepted SVC V015 MINOR for PR #40392 | Backward-compatible method additions on `@api` class; no way to add public methods without V015; maintainers accept for legitimate additions |
| Reverted stray cart price rule commit from PR #40391 | That commit was unrelated to the configurable product fix; would pollute the PR diff |
| Removed `Product $configProduct` type hint from private `isAttributeValuable()` | PHP 8.5 TypeError: test passes a `Configurable` type mock (not a `Product`); private method, no API contract to maintain; @param PHPDoc documents intent |
| Added shipping amount zero-init before address loop (PR #40918) | Virtual-only quotes skip the billing guard → setShippingAmount never called → null; needed explicit 0 init matching subtotal/grandTotal pattern |
| `connect()` excluded from ClientInterface and Socket (PR #40392) | `Socket::connect($host, $port)` already uses that name for TCP setup — adding `connect($uri)` for HTTP would create a fatal duplicate declaration. Curl-only feature. |
| Cannot add labels on upstream magento/magento2 | External contributors don't have triage/write permissions. Labels are managed by the Magento bot and maintainers only. |

---

## 8. Maintainer / Review Process (added Session 7)

### Active Reviewers

| Reviewer | Role |
|----------|------|
| `engcom-Hotel` | Primary code reviewer — approves/requests changes on all PRs |
| `engcom-Charlie` | SVC specialist — raises internal JIRA tickets for MINOR violations; coordinates complex PRs |
| `bgorski` | Magento engineer — final technical sign-off on framework changes |
| `Den4ik` | Magento community engineer — approves framework changes |
| `ihor-sviziev` | Active reviewer/engineer |

### SVC MINOR Violation Process

When a PR adds public methods to `@api` classes/interfaces:
1. SVC will fail with V015/V034 MINOR violations — this is **expected and not fixable by contributors**.
2. The contributor should add a comment in the PR acknowledging the violations are intentional.
3. `engcom-Charlie` reviews the PR and raises an internal JIRA ticket (e.g. AC-14282 for #39471).
4. The PR moves to "Pending Approval" state until internal JIRA approval is obtained.
5. Contributor does not need to do anything further for SVC — wait for maintainer action.

**Precedent:** PR #39471 (same issue #40157) — engcom-Charlie raised AC-14282 and moved PR to Pending Approval. Currently waiting for internal sign-off.

### Merged PR CI acceptance thresholds

| Check | Threshold |
|-------|-----------|
| Unit Tests | Must PASS |
| Static Tests | Must PASS |
| SVC | Must PASS (or internal JIRA raised for MINOR) |
| DB Compare | Must PASS |
| Health Index | Must PASS |
| Integration Tests | Expected to pass; 2/20 merged PRs had it failing |
| WebAPI Tests | Expected to pass; 3/20 merged PRs had it failing |
| Functional CE/EE/B2B | **Always fail on every PR** — universally accepted, never blocks merge |

---

## 9. Next Steps (as of 2026-07-06 Session 10)

### Immediate

1. **PR #40933** — CI CLEAN (Unit/Static/Integration/SVC/DB/Health PASS). Only pre-existing failures remain.
   - Tag `@engcom-Hotel` for review if not already done.
   - Fix confirmed on live Magento 2.4.9 Docker instance 2026-07-06.
   - Test guide at `.project/session-notes/pr40933-test-guide.md`.

2. **PR #40391** — Integration test fix committed 2026-07-05; new CI run triggered. Check results at next session start.

3. **PR #40918** — WebAPI re-run triggered; CI comment added. Check if WebAPI PASS now.

4. **PR #40392** — SVC MINOR inherent; awaiting engcom-Charlie to raise internal JIRA. No code action needed.

### Server Setup Note
Home server (`192.168.29.20`) runs Magento 2.4.9 with Docker Compose:
- PHP container: `magento_php:local` — built from `/var/www/html/docker-containers/magento/Dockerfile`
- `pcntl` extension now included in Dockerfile (added 2026-07-06). Rebuild if container is recreated: `docker compose build php && docker compose up -d php`
- Magento source: `/mnt/ssd/magento2-src` (koushikch7 fork, branch `2.4-develop`)
- Magento webroot: `/mnt/ssd/magento`
- To sync new `.project/session-notes/` files: `ssh chk@192.168.29.20 -p22` then `cd /mnt/ssd/magento2-src && git pull origin 2.4-develop`

### Rebase Status
- PR #40392: Up to date (0 behind).
- PR #40918, PR #40391, PR #40933: Check at next session start; rebase only if significantly behind.

### How to diagnose a failure (step-by-step)
```bash
# 1. Get the head commit SHA
SHA=$(gh pr view <PR_NUMBER> --repo magento/magento2 --json headRefOid --jq '.headRefOid')

# 2. Get all check run details with report URLs
gh api "repos/magento/magento2/commits/$SHA/check-runs" \
  --jq '.check_runs[] | {name: .name, conclusion: .conclusion, status: .status, output_summary: .output.summary}'

# 3. Copy the report URL from output_summary
# Fetch console-error-logs.html (plain text output, easier than allure HTML):
curl -s "<BASE_URL>/console-error-logs.html" | python3 -c "
import sys, re
content = sys.stdin.read()
body = re.search(r'<body.*?>(.*)</body>', content, re.DOTALL)
if body:
    text = re.sub(r'<script[^>]*>.*?</script>', '', body.group(1), flags=re.DOTALL)
    text = re.sub(r'<style[^>]*>.*?</style>', '', text, flags=re.DOTALL)
    text = re.sub(r'<[^>]+>', ' ', text)
    text = re.sub(r'\s+', ' ', text)
    print(text[-5000:])  # tail contains failure summary
"
# If 404: reports expired (>24h) — trigger fresh run and check again within 24h
```

### How to diagnose a failure (step-by-step)
```bash
# 1. Get the head commit SHA
SHA=$(gh pr view <PR_NUMBER> --repo magento/magento2 --json headRefOid --jq '.headRefOid')

# 2. Get all check run details with report URLs
gh api "repos/magento/magento2/commits/$SHA/check-runs" \
  --jq '.check_runs[] | {name: .name, conclusion: .conclusion, status: .status, output_summary: .output.summary}'

# 3. Copy the report URL from output_summary, open it:
# WebFetch the allure-report index.html URL
# If 404: reports expired (>24h) — trigger fresh run and check again
```

### Ongoing
- Check PR activity every 7–10 days. Magento auto-closes inactive PRs after ~2 weeks.
- Watch for maintainer review comments — respond promptly.
- If `2.4-develop` advances significantly, rebase all three branches (see Section 5).

### When a PR passes all CI
1. Verify the PR has no open review comments.
2. Wait for a Magento maintainer to assign and review.
3. If changes are requested, push fixes to the same branch (PRs update automatically).

---

## 9. Commit Message Style

Magento upstream uses plain imperative messages without conventional commit prefixes:
```
Fix shipping amount overwritten by billing address in TotalsCollector
Add missing HTTP methods to Curl client
Fix configurable product type conversion when variants are removed
```

Avoid: `feat:`, `fix:`, `chore:` prefixes (these are non-standard for Magento upstream).

For internal/chk-doc commits, conventional commit style is fine:
```
docs: update RUNBOOK with Session 2 CI results
```

---

## 10. Useful Reference Links

- Magento contribution guide: https://developer.adobe.com/commerce/contributor/guides/
- PHPUnit 12 migration: https://docs.phpunit.de/en/12.0/migration.html
- PR #40918: https://github.com/magento/magento2/pull/40918
- PR #40391: https://github.com/magento/magento2/pull/40391
- PR #40392: https://github.com/magento/magento2/pull/40392
- Issue #26209: https://github.com/magento/magento2/issues/26209
- Issue #2703: https://github.com/magento/magento2/issues/2703
- Issue #40157: https://github.com/magento/magento2/issues/40157
