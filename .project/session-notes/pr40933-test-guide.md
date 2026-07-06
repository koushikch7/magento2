# PR #40933 — Test Guide: Parallel SCD Exit Code Propagation

**Issue:** https://github.com/magento/magento2/issues/22883
**PR:** https://github.com/magento/magento2/pull/40933
**Branch:** `fix/issue-22883-parallel-deploy-exit-code`
**Verified:** 2026-07-06 on Magento 2.4.9 Docker (`192.168.29.20`)

---

## Issue Description

`bin/magento setup:static-content:deploy -f --jobs N` (`--jobs` > 1 enables parallel mode) always exits with code `0`, even when one or more child deploy processes fail. This means CI pipelines and deployment scripts cannot detect partial or total SCD failures when running in parallel mode.

---

## Root Cause

`app/code/Magento/Deploy/Process/Queue.php` (in the Magento module, not vendor) forks child processes via `pcntl_fork()`. Each child runs a deploy job independently. When a child fails, the parent detects this inside `isDeployed()` using `pcntl_waitpid()` + `pcntl_wexitstatus()` — but only logs the failure. The `$returnStatus` variable (used to build the exit code) was never updated from `0`.

Key code path (before fix):
1. `process()` calls `awaitForAllProcesses()` → which calls `isDeployed()` for each forked child.
2. `isDeployed()` calls `pcntl_wexitstatus($status)` — detects non-zero → **only logs**.
3. `process()` returns `0` regardless.

**PHP exceptions cannot cross `fork()` boundaries.** The only way to propagate child failures to the parent is via exit codes through `pcntl_wexitstatus`.

---

## The Fix (PR #40933)

Three changes to `app/code/Magento/Deploy/Process/Queue.php`:

1. **New property** `private bool $hasErrors = false;`
2. **`isDeployed()`** — sets `$this->hasErrors = true` when `pcntl_wexitstatus` is non-zero.
3. **`process()`** — after `awaitForAllProcesses()`, throws `\RuntimeException` if `$this->hasErrors`.
4. **`execute()` child branch** — wraps `deploy()` in `try/catch(\Throwable)` → logs error and `exit(1)`.

---

## Prerequisites

- Magento 2.4+ installation with `bin/magento` accessible.
- PHP with `pcntl` extension enabled (required for parallel mode). Verify: `php -m | grep pcntl`
- For Docker: `pcntl` must be compiled into the PHP-FPM image.
  - In `/var/www/html/docker-containers/magento/Dockerfile`, ensure `pcntl` is in `docker-php-ext-install`:
    ```
    bcmath ctype dom fileinfo ftp gd iconv intl mbstring \
    pdo_mysql simplexml soap xsl zip sockets pcntl \
    ```
  - Rebuild if needed: `docker compose build php && docker compose up -d php`
- Access to `vendor/magento/module-deploy/Process/Queue.php` and `vendor/magento/module-deploy/Process/DeployPackage.php`.

---

## Steps to Reproduce the Bug (Without Fix)

### 1. Ensure original vendor Queue.php is in place

```bash
# On server (adjust path for your setup):
ssh chk@192.168.29.20 -p22
cd /mnt/ssd/magento

# Verify Queue.php does NOT have the hasErrors property
grep -n "hasErrors" vendor/magento/module-deploy/Process/Queue.php
# Expected: no output (property doesn't exist before fix)
```

### 2. Inject a forced failure into DeployPackage::deploy()

```bash
# Back up the original
cp vendor/magento/module-deploy/Process/DeployPackage.php \
   vendor/magento/module-deploy/Process/DeployPackage.php.bak

# Edit the deploy() method — add throw as the first line of the method body
# Find the line: public function deploy(
# Inside the method body, add:
#   throw new \RuntimeException('FORCED TEST FAILURE — remove after testing');
```

Using sed (adjust line number to match actual `deploy()` method body start — verify with grep first):
```bash
grep -n "public function deploy(" vendor/magento/module-deploy/Process/DeployPackage.php
# Then manually edit with nano or sed to add the throw
```

### 3. Run SCD in parallel mode and observe exit code

```bash
cd /mnt/ssd/magento

# Capture output and exit code separately (IMPORTANT: do not pipe — pipe resets $?)
docker exec -u www-data magento_php \
    php bin/magento setup:static-content:deploy -f --jobs 2 \
    > /tmp/scd_out.txt 2>&1

code=$?
tail -10 /tmp/scd_out.txt
echo ""
echo ">>> EXIT CODE: $code"
```

**Expected (bug present):** Error messages in output about `FORCED TEST FAILURE`, but `EXIT CODE: 0`.

---

## Steps to Confirm the Fix

### 1. Apply Queue.php from PR #40933

```bash
# Fetch the PR branch (from server, in /mnt/ssd/magento2-src)
cd /mnt/ssd/magento2-src
git fetch origin fix/issue-22883-parallel-deploy-exit-code
git checkout -b fix/issue-22883-parallel-deploy-exit-code FETCH_HEAD

# Copy the fixed Queue.php to vendor
cp app/code/Magento/Deploy/Process/Queue.php \
   /mnt/ssd/magento/vendor/magento/module-deploy/Process/Queue.php
```

### 2. Verify the fix is in place

```bash
grep -n "hasErrors" /mnt/ssd/magento/vendor/magento/module-deploy/Process/Queue.php
# Should show: private bool $hasErrors = false;  and two references
```

### 3. Run SCD again with the injected failure still active

```bash
cd /mnt/ssd/magento

docker exec -u www-data magento_php \
    php bin/magento setup:static-content:deploy -f --jobs 2 \
    > /tmp/scd_out.txt 2>&1

code=$?
tail -10 /tmp/scd_out.txt
echo ""
echo ">>> EXIT CODE: $code"
```

**Expected (fix working):**
- Error output referencing `FORCED TEST FAILURE`
- Line: `Static content deploy failed: one or more packages could not be deployed. Check the log for details.`
- `EXIT CODE: 1`

---

## Cleanup After Testing

```bash
# Restore original DeployPackage.php (remove injected failure)
cp vendor/magento/module-deploy/Process/DeployPackage.php.bak \
   vendor/magento/module-deploy/Process/DeployPackage.php
rm vendor/magento/module-deploy/Process/DeployPackage.php.bak

# Restore original Queue.php (remove fix from vendor — it lives in app/code/ in the PR)
# Option A: git checkout if magento webroot is a git repo
# Option B: reinstall via composer
# Option C: manually revert the hasErrors additions

# Switch magento2-src back to 2.4-develop
cd /mnt/ssd/magento2-src
git checkout 2.4-develop
```

---

## Actual Test Results (2026-07-06)

**Environment:**
- Server: `192.168.29.20`
- Magento version: 2.4.9
- PHP: 8.3-fpm-bookworm with pcntl
- Container: `magento_php:local` (rebuilt 2026-07-06 with pcntl)

**Without fix:**
```
[RuntimeException]
FORCED TEST FAILURE — remove after testing
... (stack trace) ...
>>> EXIT CODE: 0       ← BUG CONFIRMED
```

**With fix (Queue.php from PR #40933):**
```
[RuntimeException]
FORCED TEST FAILURE — remove after testing
...
Static content deploy failed: one or more packages could not be deployed. Check the log for details.
>>> EXIT CODE: 1       ← FIX CONFIRMED
```

---

## Unit Tests

Two tests added to `app/code/Magento/Deploy/Test/Unit/Process/QueueTest.php`:

**`testIsDeployedSetsHasErrorsOnChildFailure`**
- Forks a real child process that calls `exit(1)`.
- Asserts `hasErrors` property is `true` after `isDeployed()` processes it.
- Skipped automatically if `pcntl_fork` unavailable.

**`testProcessThrowsRuntimeExceptionWhenHasErrors`**
- Sets `hasErrors = true` via reflection.
- Asserts `process()` throws `\RuntimeException`.
- Does not require `pcntl_fork` — pure logic test.

Run locally:
```bash
cd /mnt/ssd/magento
docker exec -u www-data magento_php \
    php vendor/bin/phpunit \
    app/code/Magento/Deploy/Test/Unit/Process/QueueTest.php \
    --filter "testIsDeployedSetsHasErrorsOnChildFailure|testProcessThrowsRuntimeExceptionWhenHasErrors" \
    -v
```
