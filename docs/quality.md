# Quality checks and test database safety

Established by [F00-T02](tasks/F00-T02.md). Read [AGENTS.md](../AGENTS.md) and the assigned task before making changes. No command here stages or commits files.

## Normal terminal checks

Run from the project root with PHP 8.4+ and the installed dependencies:

```powershell
composer validate --strict
php vendor/bin/pint --dirty --format agent
composer analyse
php artisan test --compact
composer test:fuzz
composer test:mysql
npm run build
git diff --check
```

Pint formats changed PHP files. Review its edits before committing. Larastan runs at level 8 across application PHP, bootstrap/app.php, configuration, database, routes and the typed test-safety/bootstrap code, without an error baseline or ignore rules. Pest closure files run through Pest; no additional Pest static-analysis plugin is installed.

Current expected counts: **61 fast tests / 469 assertions**, **3 fuzz tests / 402 assertions**, **63 MySQL-profile tests / 476 assertions**. Counts grow as later features add tests. Both full profiles include the fuzz suite; the fuzz command provides focused replay. The fast profile uses only in-memory SQLite. The MySQL profile runs Unit, Feature, Fuzz and MySql suites.

The initial two scaffold tests are retained. The new property checks the supplied User model's secret hiding; it is not proof of future employee-safe review/PDF permissions.

## Isolated local MySQL

This workspace now has a newly created disposable `morgan_hr_skills_test` database and a private ignored `.env.testing` with local connection details. Its contents must never be pasted into chat or committed. The ordinary development database remains separate.

`composer test:mysql` can recreate tables in **that test database** through Laravel's test database-refresh trait. Do not store useful/manual/live data there. Run only one MySQL suite at a time; parallel database naming/isolation is not configured.

For another checkout/machine:

1. Create an empty local MySQL database using your normal database tool:

   ```sql
   CREATE DATABASE morgan_hr_skills_test
       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Copy `.env.testing.example` to `.env.testing` only if the private file does not already exist. Set local test credentials/port. A dedicated account limited to the test schema can be used.
3. Leave DB_URL empty. Use localhost or 127.0.0.1, without a socket or read/write connection override.
4. Run `composer test:mysql`. Do not repoint the supplied development environment or run a development database reset to make tests pass.

The two PHPUnit profiles select the database driver through the test-only MORGAN_TEST_DB_CONNECTION constant and share tests/bootstrap.php. They keep the test environment/key and non-external cache/session/mail/queue settings. DB_DATABASE and DB_URL are deliberately not overwritten by PHPUnit: the bootstrap first records their inherited presence from getenv, $_ENV and $_SERVER. It retains only variable names, captures once before Laravel loads dotenv, and never logs values. Test bootstrapping validates and installs its fixed connection before providers and test migration traits run:

- Cached configuration is refused before Laravel loads it. If encountered, deliberately run `php artisan config:clear` in your local checkout and retry; the test command no longer clears caches silently.
- Only in-memory SQLite or the specifically named local MySQL database is allowed. The selected connection becomes the only configured SQL connection; attempts to use other named connections fail before connecting. SQLite always uses :memory: with no URL, even if caller environment settings differ.
- Development database names are read privately from literal .env values (DB_DATABASE or the decoded path of a literal DB_URL) and cannot equal the MySQL test database under a case-insensitive comparison, including decoded URL paths. The parser never resolves names against the PHPUnit-mutated process environment.
- MySQL refuses missing/blank identities and any variable reference in the selected name/URL, including same-file references whose defaults could be overridden externally. URLs must have a scheme, host and database path; query options are refused because they can override the path. Unsupported/unknown identities stop the suite; do not bypass the guard or repoint development to make it pass. In-memory SQLite can run without a known development identity.
- Non-test environments, remote hosts, URLs, socket/read/write overrides and missing connections fail closed.
- MySQL requires an explicit private .env.testing; credentials cannot silently fall back to the normal development file.
- MySQL requires a captured caller environment with no inherited DB_DATABASE or DB_URL, including empty values. Keep these identity settings in the private files. If the guard reports an inherited override, use a clean terminal or deliberately remove only these variables from the launching shell; do not change the development database or bypass the guard. A missing snapshot also refuses MySQL. Values later loaded by dotenv do not change the original snapshot.

The safety guard applies when tests extend the project TestCase. New Laravel/database tests must use that base. Framework-free unit tests must not open their own unguarded database connections. Test-only cached-config traits are deliberately unsupported by this bootstrap because cached configuration is refused.

## Reproducible fuzz tests

Uses **Eris 1.2.1** with the installed Pest 5 / PHPUnit 13. No custom random/shrinking engine. The current property makes 200 evaluations with generated ASCII strings, checking array and JSON serialisation. Empty secrets and a shrunk non-empty case are retained explicitly. Unicode, scoring, parsers and workflow properties belong to their relevant future tasks.

Default seed in both profiles: `20261015`. To replay or explore another seed in PowerShell:

```powershell
$env:ERIS_SEED = '20261015'
composer test:fuzz
Remove-Item Env:ERIS_SEED
```

A failing Eris test prints its seed. Its generic diagnostic mentions PHPUnit; use the Composer/Pest command above for this project. To focus further in PowerShell, keep the full Pest filter in literal single quotes:

```powershell
composer test:fuzz -- --filter='never serializes authentication secrets'
```

This command must select **one test** (400 assertions from 200 generated evaluations). If it selects no tests, check the filter and quoting. Do not add backslashes before the quotes.

Retain the failing seed, commit/working-tree scope, locked dependencies, generator settings and minimal failing input before fixing a property failure. Add a small explicit regression alongside generated coverage. Explore additional seeds deliberately; a fixed seed is reproducible, not exhaustive.

F00-T02 proof: a temporary per-instance password-visibility fault for passwords of length at least four failed twice with seed 20261015, 95 assertions and the same shrunk password `?lLS`. The fault was removed; the four-character case is now an explicit passing regression. This was a deliberate harness experiment, not a discovered production vulnerability.

## Build and package decisions

PHP is declared `^8.4`; the existing dev dependency graph additionally requires the compatible PHPUnit patch level (currently PHP >=8.4.1). The observed CLI is 8.4.5. New dev dependencies are Larastan 3.13.0, PHPStan 2.3.1, Eris 1.2.1 and Larastan's SQL parser dependency. Existing packages were not upgraded or downgraded.

Instrument Sans remains configured through the existing Vite pipeline. Optional optimised font fallbacks are disabled explicitly, so the build no longer warns about missing fontaine and no frontend dependency was added. A cold build still needs the configured remote font provider; shared-hosting deployment uses prepared assets.

Static analysis exposed a boolean/string APP_URL ambiguity in the scaffold filesystem configuration. It now checks that the configured URL is a string before using it, rather than suppressing analysis errors.

## Evidence and next gate

Agent-run and user-run checks are complete for F00-T02. Johan supplied passing terminal output on 15 October 2026: 19 fast tests / 418 assertions, 3 fuzz tests / 402 assertions and 21 MySQL tests / 425 assertions, plus valid Composer configuration, Pint, analysis and build. Git reported only a line-ending normalisation warning. The full user record and implementation commit are in [F00-T02](tasks/F00-T02.md); the F00-T01 user baseline remains historical evidence in [baseline](baseline.md).

Independent review at `a2da616` raised two correction items. [F00-C01](tasks/F00-C01.md) records the database-isolation correction; [F00-C02](tasks/F00-C02.md) clarifies the PowerShell filter example and records an actual run selecting 1 test / 400 assertions. C02 inspection confirmed the original committed example already used valid ordinary double quotes without backslash escapes. [F00-C03](tasks/F00-C03.md) closes the subsequently confirmed P1 inherited-override and case-equivalent-name gaps; its task records current checks and manual commands. After user verification, open a fresh F00 correction-review chat using [the index](tasks/README.md) and record foundation acceptance before F01-T01. The preceding F00-T02 results are historical evidence; pending correction user runs are not passes.
