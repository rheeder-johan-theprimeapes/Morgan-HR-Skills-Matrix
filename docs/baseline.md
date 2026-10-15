# F00-T01 baseline — 15 October 2026

Historical observations of the supplied base before F00-T02, not evidence that planned HR features exist. Current tooling/commands are in [the quality guide](quality.md). See the [specification](specification.md) and [task index](tasks/README.md).

## Repository

- [Private repository](https://github.com/rheeder-johan-theprimeapes/Morgan-HR-Skills-Matrix), branch `main`, commit `1b2a8930014a98cfa9de9d7479573de91ce929a8` (`base install`).
- A tracked `package-lock.json` modification predated this round and is preserved. Initial SHA-256: `e1339ab72fd3f9cd29aa64a14594b9fd3d78a7e274bbb83410af75d4a5aaa0d2`.
- No branch, worktree, stage, commit, push or dependency install. No private sources, credentials or employee/pay data copied into Git.
- Generated Boost instructions in `AGENTS.md` are preserved; project delivery rules appended outside the block.
- `.ai/rules` is absent. No full convention-inference sweep was requested/run.
- `CLAUDE.md` retains generic bootstrap text. PHP/Boost are already installed; do not reinstall them.

## Observed versions

CLI, Composer installed-package output/metadata and frontend lock data:

| Component | Version |
| --- | --- |
| PHP CLI | 8.4.5 |
| Laravel | 13.36.0 |
| Boost | 2.10.3 |
| Pint | 1.32.1 |
| Pest / Laravel plugin | 5.3.2 / 5.0.1 |
| PHPUnit | 13.4.2 |
| Node.js | 22.19.0 |
| Vite | 8.3.4 |
| Tailwind CSS / Vite plugin | 4.3.3 |
| Laravel Vite plugin | 3.2.0 |

Livewire, Filament, Larastan and the chosen fuzz/property support are not installed yet. Preserve Pest 5.

Composer currently declares PHP `^8.3`. F00-T02 must align the manifest with the approved PHP 8.4+ floor and installed dependencies. Observed runtime already satisfies it.

## Existing behaviour

- Application route file renders Laravel welcome at `/`; framework health endpoint configured as `/up`.
- Standard users/cache/jobs migrations and scaffold user model, including Laravel 13 attribute conventions.
- No organisation/matrix/scoring/PDR/pay/import/export/scoped-permission implementation.
- Two scaffold tests: trivial unit assertion and welcome-page response. They prove no HR-domain/security behaviour.
- PHPUnit uses SQLite `:memory:`, array cache/session/mail and synchronous queue. No isolated MySQL profile.
- npm scripts: `build` runs `vite build`; `dev` runs `vite`. Vite fetches Instrument Sans fonts.
- Composer `setup` includes key generation/migration and was not run against this supplied installation.

## Agent-run results

These commands/checks ran in this documentation round:

| Check | Actual result and limitation |
| --- | --- |
| `php -v` | Exit 0; PHP 8.4.5. |
| `php artisan --version` | Exit 0; Laravel Framework 13.36.0. |
| `composer show --direct --format=json` | Exit 0 via the existing Composer PHAR. Tool subprocess needed APPDATA supplied; global environment unchanged. |
| `php artisan route:list --except-vendor --json` | Exit 0; welcome application route. Vendor-filtered output does not enumerate all framework routes. |
| `php artisan migrate:status --no-interaction` | Exit 0; users/cache/jobs each batch 1, Ran. Read-only; no migration/reset executed. |
| HTTP GET, user-provided local application | 200, Laravel welcome content. No interactive/accessibility journey proven. |
| `php artisan test --compact` | Exit 0; 2 passed, 2 assertions; scaffold coverage only. |
| `node node_modules/vite/bin/vite.js build` | Exit 0; production build succeeded using the Vite command behind the npm script. |
| `node --version` | Exit 0; v22.19.0. |

Build emitted a non-fatal warning that optional `fontaine` is missing for optimised font fallbacks. No package added. F00-T02 should decide whether to retain/address it and consider reproducible font availability.

Only ignored generated build/test outputs were produced. No application PHP, dependency manifests/lockfiles, migrations or database records changed by F00-T01.

## User-run verification

From the project root in your normal terminal:

```powershell
php artisan test --compact
npm run build
php artisan migrate:status --no-interaction
```

Expected: 2 scaffold tests pass; build exits successfully (optional fontaine warning may appear); 3 default migrations show Ran. Do not run `migrate:fresh`, `db:wipe` or the bootstrap setup script for this check.

**User results received on 15 October 2026**, from Johan’s pasted PowerShell output. These are user-run results, separate from the agent-run checks above.

| Actual user command | Reported result |
| --- | --- |
| `php artisan test` | 2 tests passed, 2 assertions; duration 3.69 seconds. Existing scaffold tests only. |
| `npm run build` | Vite 8.3.4 built successfully in 1.89 seconds. The optional fontaine warning appeared; it did not prevent the build. |
| `php artisan migrate:status` | Users, cache and jobs migrations each show batch 1, Ran. |

The user ran the equivalent test/status commands without the optional display/non-interaction flags. No rerun is needed for this baseline. F00-T02 retains the fontaine-warning decision.

Repository follow-up: root `.gitignore` excluded `docs/`, leaving these repository handoff records untracked and hidden from normal Git status. Removed that single exclusion so the specification, task contracts and evidence can be included in Johan’s commit. No files were staged or committed.

## Next task

F00-T02 aligns the PHP floor, establishes Pint/Larastan/Pest/fuzz/isolated-MySQL/build checks and exact commands, proves seed replay/shrinking, documents test-database safety and assesses the build warning. Preserve existing lockfile work; do not reinstall the base or build identity features in that task.
