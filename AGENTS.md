<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>

# Morgan HR Skills Matrix delivery contract

These user-approved project rules supplement the generated Boost guidelines above. Keep this section outside the managed block when Boost updates it.

## Start each chat

1. Read this file, [the task index](docs/tasks/README.md), the assigned task, its prerequisite contracts and relevant [specification](docs/specification.md) sections.
2. Inspect the current Git state and actual implementation. Preserve user-owned changes. [The baseline](docs/baseline.md) records the original supplied installation.
3. Confirm prerequisites and required feature acceptance. Use only relevant skills; do not repeat a full skill/convention audit in every chat.
4. Implement one bounded task with meaningful verification, then update its evidence/index and hand back. Stop at that task boundary.

## Fixed engineering rules

- PHP 8.4+, Laravel 13, MySQL, Blade, selective Livewire and Filament. Use installed-compatible APIs; no SPA navigation, Filament SPA mode or `wire:navigate`.
- Keep Laravel conventions, thin controllers, focused transactional actions, validation and server-side policies. Avoid speculative abstraction. Follow the project Laravel and testing skills for applicable work.
- SDD means specification-driven development: update affected contracts before behaviour changes, test meaningful behaviour, then implement and verify it.
- Scoring is HR-controlled and explainable. Submitted reviews are immutable revisions. Pay decisions are human. Employee-facing responses, Livewire state, documents and exports must contain only permitted fields.
- Shared 20i hosting has no sudo, npm, Docker or persistent worker. Build locally/CI and verify actual hosting capabilities before deployment.
- Never run destructive tests/migrations against the connected development database. Use an explicitly isolated test database and confirm configuration/cache safety.
- Never commit secrets, real employee/pay records, private source attachments or generated private reports.

## User-owned delivery workflow

- Johan supplied the base: do not scaffold or reinstall it. Required tooling/features are authorised within their task; unrelated dependency or architecture changes need an explained decision.
- Work sequentially in this shared checkout. Do not create branches/worktrees unless Johan asks. Johan opens new chats and handles staging, commits, pushes and merges.
- Repository Markdown is the durable source for specifications, tasks, decisions, verification and handoffs. The approved `docs/` structure is intentional.
- Use states planned → ready → in progress → verified; blocked identifies a missing prerequisite. Verified means ready for user review/commit, not feature acceptance.
- At feature boundaries Johan opens a fresh review chat. Review reports findings without implementation. Number correction tasks, resolve material findings and record acceptance before dependent feature work.
- Split an oversized task and update its dependencies/contracts before continuing. Do not silently build the next task.
- User instructions about one task per chat, this checkout and user commits override skill defaults for continuous execution, worktrees, temporary-only handoffs and automatic commits. Do not recreate an already completed installation step because generic bootstrap guidance suggests it.

## Verification and handoff

Run the narrowest meaningful automated checks; use the exact quality commands in [the quality guide](docs/quality.md), established in F00-T02. Do not invent passing results. Preserve failing seeds, shrink useful fuzz failures and retain regression examples.

For each round, give Johan exact terminal commands, expected outcomes and any relevant manual UI scenarios. Record agent-run results and user-run results separately; a requested/pending user run is not a pass. After focused tests, Johan also runs `php artisan test --compact` before committing. Build when frontend/assets change.

End with changed behaviour/files, actual verification, limitations and the next ready task. Leave all commits to Johan. Feature-review guidance and starter prompts are in [the task index](docs/tasks/README.md).

## Autonomous delivery instruction — 16 October 2026

Johan subsequently instructed: “i want you to now test and run everything following the tasks, until the systems if fully built and only get me involved if you need my input or direction”.

- Continue sequentially through the existing task backlog in this shared checkout; the earlier one-task-per-chat and user-run verification handoffs no longer require stopping.
- Run the focused and full quality checks as the agent and record them as agent-run evidence. Do not relabel them as user-run results.
- Use independent agent reviews at feature boundaries, resolve material findings and record the technical progression decision. Formal HR/business acceptance and live-provider/hosting verification remain distinct and must not be invented.
- The existing specification/task contracts are the approved scope. Make and record routine implementation decisions without repeatedly requesting design/plan approval. Ask only for missing business direction, required external access or a consequential action outside the authorised build.
- Preserve the isolated-database safeguards, private-data rules, no-SPA architecture and existing source changes. Leave branches, staging, commits, pushes and merges to Johan.

Follow-up direction on 16 October 2026: “for now avoid ms365 and just do local logins”. Defer F01-T02/Microsoft integration; native accounts and organisation permissions proceed without that dependency.
