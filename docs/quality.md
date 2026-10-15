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

Pint formats changed PHP files. Review its edits before committing. Larastan runs at level 8 across application PHP, bootstrap/app.php, configuration, database, routes and the typed test-safety code, without an error baseline or ignore rules. Pest closure files run through Pest; no additional Pest static-analysis plugin is installed.

Current expected counts: **19 fast tests / 418 assertions**, **3 fuzz tests / 402 assertions**, **21 MySQL-profile tests / 425 assertions**. Counts grow as later features add tests. Both full profiles include the fuzz suite; the fuzz command provides focused replay. The fast profile uses only in-memory SQLite. The MySQL profile runs Unit, Feature, Fuzz and MySql suites.

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

The two PHPUnit profiles force the test environment/key, database name/driver/URL and non-external cache/session/mail/queue settings. Test bootstrapping checks configuration before providers and test migration traits run:

- Cached configuration is refused before Laravel loads it. If encountered, deliberately run `php artisan config:clear` in your local checkout and retry; the test command no longer clears caches silently.
- Only in-memory SQLite or the specifically named local MySQL database is allowed.
- Development database names are read privately from .env (including a DB_URL database component) and cannot equal the MySQL test database.
- Non-test environments, remote hosts, URLs, socket/read/write overrides and missing connections fail closed.
- MySQL requires an explicit private .env.testing; credentials cannot silently fall back to the normal development file.

The safety guard applies when tests extend the project TestCase. New Laravel/database tests must use that base. Framework-free unit tests must not open their own unguarded database connections. Test-only cached-config traits are deliberately unsupported by this bootstrap because cached configuration is refused.

## Reproducible fuzz tests

Uses **Eris 1.2.1** with the installed Pest 5 / PHPUnit 13. No custom random/shrinking engine. The current property makes 200 evaluations with generated ASCII strings, checking array and JSON serialisation. Empty secrets and a shrunk non-empty case are retained explicitly. Unicode, scoring, parsers and workflow properties belong to their relevant future tasks.

Default seed in both profiles: `20261015`. To replay or explore another seed in PowerShell:

```powershell
$env:ERIS_SEED = '20261015'
composer test:fuzz
Remove-Item Env:ERIS_SEED
```

A failing Eris test prints its seed. Its generic diagnostic mentions PHPUnit; use the Composer/Pest command above for this project. To focus further, append a Pest filter:

```powershell
composer test:fuzz -- --filter="never serializes authentication secrets"
```

Retain the failing seed, commit/working-tree scope, locked dependencies, generator settings and minimal failing input before fixing a property failure. Add a small explicit regression alongside generated coverage. Explore additional seeds deliberately; a fixed seed is reproducible, not exhaustive.

F00-T02 proof: a temporary per-instance password-visibility fault for passwords of length at least four failed twice with seed 20261015, 95 assertions and the same shrunk password `?lLS`. The fault was removed; the four-character case is now an explicit passing regression. This was a deliberate harness experiment, not a discovered production vulnerability.

## Build and package decisions

PHP is declared `^8.4`; the existing dev dependency graph additionally requires the compatible PHPUnit patch level (currently PHP >=8.4.1). The observed CLI is 8.4.5. New dev dependencies are Larastan 3.13.0, PHPStan 2.3.1, Eris 1.2.1 and Larastan's SQL parser dependency. Existing packages were not upgraded or downgraded.

Instrument Sans remains configured through the existing Vite pipeline. Optional optimised font fallbacks are disabled explicitly, so the build no longer warns about missing fontaine and no frontend dependency was added. A cold build still needs the configured remote font provider; shared-hosting deployment uses prepared assets.

Static analysis exposed a boolean/string APP_URL ambiguity in the scaffold filesystem configuration. It now checks that the configured URL is a string before using it, rather than suppressing analysis errors.

## Evidence and next gate

Agent-run and user-run checks are complete for F00-T02. Johan supplied passing terminal output on 15 October 2026: 19 fast tests / 418 assertions, 3 fuzz tests / 402 assertions and 21 MySQL tests / 425 assertions, plus valid Composer configuration, Pint, analysis and build. Git reported only a line-ending normalisation warning. The full user record and implementation commit are in [F00-T02](tasks/F00-T02.md); the F00-T01 user baseline remains historical evidence in [baseline](baseline.md).

After review/commit, open a fresh F00 review chat using [the index](tasks/README.md). Resolve material findings and record foundation acceptance before F01-T01. No HR feature was implemented in this tooling round.
