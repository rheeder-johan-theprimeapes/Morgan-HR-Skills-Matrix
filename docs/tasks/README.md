# Delivery task index

Current round: **Autonomous delivery — F06-T02 in progress**. F05 PDR workflow and its technical review corrections are verified. Latest profiles: SQLite 266 / 2,626; MySQL 285 / 2,754; fuzz 5 / 1,302; analysis, Pint and build passed. Microsoft remains deferred; commits remain Johan’s responsibility. Preview-only SLT browser permissions are pending explicit approval; automated SLT/release checks pass.

Read [project instructions](../../AGENTS.md), [the specification](../specification.md), [baseline evidence](../baseline.md) and the assigned task. [The template](TEMPLATE.md) defines the eight required contract sections. [Skills guidance](../skills.md) preserves requested sources without requiring every skill in every chat.

## Delivery rules and states

One independently verifiable behaviour per task, across the database/application/UI/tests needed for that behaviour. Under Johan’s 16 October 2026 autonomous instruction, continue sequentially in the shared checkout and run checks as the agent. Johan handles staging/commits.

- **planned:** not yet eligible or not yet selected.
- **ready:** prerequisites and necessary external inputs/feature gates are satisfied.
- **in progress:** the one task currently being implemented.
- **verified:** actual checks support completion and it is ready for Johan's review/commit.
- **blocked:** a specific prerequisite/input is missing; record what and which work remains possible.

Task verification does not equal HR/business acceptance. Under the autonomous instruction, use an independent read-only agent review at a feature boundary, resolve material findings and record the technical progression decision before dependent implementation. Human acceptance/live activation remain explicit external gates. Internal task dependencies are the exact implementation prerequisites. The feature gate is an additional quality gate.

Small synthetic fixtures can accompany earlier tasks; F09-T01 is the final integrated demonstration dataset. Run F10-T01 early after F00 acceptance when hosting access is available; it need not wait for F09.

## Task backlog

| Task | State | Complete behaviour | Prerequisites |
| --- | --- | --- | --- |
| [F00-T01 — Baseline and specification](F00-T01.md) | verified | Establish a trustworthy baseline and repository records that a fresh chat can use without conversation history. | Supplied base and repository |
| [F00-T02 — Quality checks](F00-T02.md) | verified | Provide repeatable quality commands for PHP, tests, generated inputs, isolated MySQL and production assets. | [F00-T01](F00-T01.md) |
| [F00-C01 — Database isolation for environment-derived names](F00-C01.md) | verified | Refuse MySQL setup when the development database identity is variable-derived, unknown or equal to the test database. | [F00-T02](F00-T02.md), review at `a2da616` |
| [F00-C02 — PowerShell-safe fuzz-filter command](F00-C02.md) | verified | Provide an explicit, verified PowerShell command that selects exactly one fuzz property. | [F00-T02](F00-T02.md), review at `a2da616` |
| [F00-C03 — Inherited overrides and schema-case isolation](F00-C03.md) | verified | Refuse inherited database identities and case-equivalent development/test schema names before destructive setup. | [F00-T02](F00-T02.md), [F00-C01](F00-C01.md), review of `df033ff` |
| [F00-C04 — Ambiguously quoted identities](F00-C04.md) | verified | Refuse nested quoting that Laravel can normalise to the disposable schema. | [F00-C03](F00-C03.md), review of `ce0c4e9` |
| [F00-C05 — Test service defaults](F00-C05.md) | verified | Install safe test service defaults before providers despite inherited shell selectors. | [F00-C03](F00-C03.md), [F00-C04](F00-C04.md), review of `ce0c4e9` |
| [F01-T01 — Platform accounts](F01-T01.md) | verified | Provisioned employees can sign in/out, including employees without email, using private HR-issued setup/recovery access. | [F00-T02](F00-T02.md) |
| [F01-T02 — Microsoft sign-in](F01-T02.md) | planned | Deferred by Johan on 16 October 2026; local logins only for current delivery. Future explicit tenant identity linking. | [F01-T01](F01-T01.md) |
| [F01-T03 — Organisation directory](F01-T03.md) | verified | Authorised HR manages employees, departments, roles, reporting lines and account links. | [F01-T01](F01-T01.md) |
| [F01-T04 — Scoped permissions](F01-T04.md) | verified | Employees, managers, HR and SLT see and change only permitted people and fields across every entry point. | [F01-T01](F01-T01.md), [F01-T03](F01-T03.md) |
| [F01-C01 — Preserve retired assignments](F01-C01.md) | verified | Retired employee edits retain existing inactive links. | F01-T03, F01-T04 review |
| [F01-C02 — Current directory validation](F01-C02.md) | verified | MySQL writes validate current catalogue and active references. | F01-T03, F01-T04 review |
| [F01-C03 — Current directory capabilities](F01-C03.md) | verified | Reject revoked directory authority in existing snapshots. | F02-C01 pattern |
| [F02-T01 — Skills catalogue](F02-T01.md) | verified | HR maintains grouped skills and rating definitions for production, office and leadership roles. | [F01-T04](F01-T04.md) |
| [F02-T02 — Targets](F02-T02.md) | verified | Users see a consistently resolved target and its source for each applicable person/skill. | [F02-T01](F02-T01.md) |
| [F02-T03 — Assessments and evidence](F02-T03.md) | verified | Employees and managers record independent dated assessments with comments/evidence and history. | [F02-T02](F02-T02.md) |
| [F02-T04 — Skills matrix](F02-T04.md) | verified | Authorised users inspect filtered ratings, target gaps and skill coverage in an accessible matrix. | [F02-T03](F02-T03.md) |
| [F02-C01 — Current catalogue capabilities](F02-C01.md) | verified | Reject stale revoked grants inside existing snapshots. | F02 review |
| [F02-C02 — Discoverable assessment history](F02-C02.md) | verified | Preserve authorised history links after retirement/duty changes. | F02 review |
| [F03-T01 — Framework publishing](F03-T01.md) | verified | HR safely drafts and publishes immutable versions of the scoring framework. | [F02-T01](F02-T01.md) |
| [F03-T02 — Explainable scoring](F03-T02.md) | verified | Users see an approved weighted score with an auditable breakdown and explicit completeness. | [F03-T01](F03-T01.md), [F02-T03](F02-T03.md) |
| [F03-C01 — Draft form integrity](F03-C01.md) | verified | Preserve stale tokens and reject partial editor payloads. | F03 review |
| [F03-C02 — Invalidated target exceptions](F03-C02.md) | verified | Keep invalid exceptions visible and blocking. | F03 review |
| [F03-C03 — Available required evidence](F03-C03.md) | verified | Verify file availability/integrity before completeness. | F03 review |
| [F04-T01 — Pay and benchmarks](F04-T01.md) | verified | Authorised users maintain actual pay and sourced benchmarks with honest annual/hourly comparisons. | [F01-T04](F01-T04.md) |
| [F04-T02 — Operational context](F04-T02.md) | verified | Authorised users record source-dated operational evidence and human context separately from scoring. | [F01-T04](F01-T04.md) |
| [F04-C01 — Concurrent correction recovery](F04-C01.md) | verified | Preserve rejected correction forms after supersession. | F04 review |
| [F05-T01 — Guided PDR drafts](F05-T01.md) | verified | Allocated employees/managers complete role-appropriate PDR drafts with objectives and pay proposals. | [F03-T02](F03-T02.md), [F04-T01](F04-T01.md) |
| [F05-T02 — Submission and HR review](F05-T02.md) | verified | Submission freezes a review revision; HR reviews or returns it with reasons and preserves resubmission history. | [F05-T01](F05-T01.md), [F04-T02](F04-T02.md) |
| [F05-T03 — SLT decisions](F05-T03.md) | verified | Authorised SLT compares HR-reviewed submissions and approves or returns recommendations safely. | [F05-T02](F05-T02.md) |
| [F05-T04 — Feedback and acknowledgement](F05-T04.md) | verified | Managers record feedback, release a safe approved outcome and employees acknowledge receipt or disagree. | [F05-T03](F05-T03.md) |
| [F05-C01 — Proposal rationale retention](F05-C01.md) | verified | Never acknowledge discarded proposal rationale. | Early F05-T01 review |
| [F05-C03 — Returned review instructions](F05-C03.md) | verified | Surface immutable HR return instructions on the successor draft. | Early F05-T02 review |
| [F05-C04 — Frozen evidence completeness](F05-C04.md) | verified | Revalidate required evidence from durable copies before submission. | Early F05-T02 review |
| [F05-C05 — Commit-aware evidence cleanup](F05-C05.md) | verified | Preserve committed copies when commit outcome or callbacks fail. | Early F05-T02 review |
| [F05-C02 — Restricted benchmark citations](F05-C02.md) | verified | Preserve inaccessible saved citation on autosave. | Early F05-T01 review |
| [F05-C06 — Internal decision review](F05-C06.md) | verified | Display approved final terms to permitted internal reviewers and route SLT-only head links to frozen revisions. | F05 final review |
| [F06-T01 — Development actions](F06-T01.md) | verified | Identified gaps/objectives become owned, dated development actions with progress and completion evidence. | [F02-T03](F02-T03.md), [F05-T04](F05-T04.md) |
| [F06-C01 — Retain terminal-conflict input](F06-C01.md) | verified | Retain stale input when a competing update closes the action. | F06-T01 review |
| [F06-C02 — Complete development revision history](F06-C02.md) | verified | Display prior plan/resource/completion details. | F06-T01 review |
| [F06-T02 — Historical documents](F06-T02.md) | in progress | Authorised users upload and retrieve historical PDRs with explicit visibility and audited access. | [F02-T03](F02-T03.md) |
| [F07-T01 — People imports](F07-T01.md) | planned | HR previews, validates and reconciles a roster import, then applies the approved result atomically. | [F01-T04](F01-T04.md) |
| [F07-T02 — Skills imports](F07-T02.md) | planned | HR imports catalogues, targets and assessments through explicit mappings and existing domain rules. | [F07-T01](F07-T01.md), [F02-T03](F02-T03.md) |
| [F07-T03 — Pay/context imports](F07-T03.md) | planned | Permitted users import pay, benchmark and operational evidence without losing units, periods or provenance. | [F07-T01](F07-T01.md), [F04-T01](F04-T01.md), [F04-T02](F04-T02.md) |
| [F08-T01 — Employee PDFs](F08-T01.md) | planned | Employees and authorised staff can retrieve a printable released review containing only employee-safe information. | [F05-T04](F05-T04.md) |
| [F08-T02 — Matrix/report exports](F08-T02.md) | planned | Authorised users export only the people/fields permitted by their matrix/report scope. | [F02-T04](F02-T04.md) |
| [F08-T03 — Payroll exports](F08-T03.md) | planned | Permitted staff export approved pay decisions for a human payroll handoff with stable identifiers and audit history. | [F05-T03](F05-T03.md) |
| [F09-T01 — Demonstration dataset](F09-T01.md) | planned | A repeatable local demonstration covers about 200 fictional employees and production/office/leadership journeys. | [F01-T04](F01-T04.md), [F02-T04](F02-T04.md), [F03-T02](F03-T02.md), [F04-T01](F04-T01.md), [F04-T02](F04-T02.md), [F05-T04](F05-T04.md), [F06-T01](F06-T01.md), [F06-T02](F06-T02.md), [F07-T02](F07-T02.md), [F07-T03](F07-T03.md), [F08-T01](F08-T01.md), [F08-T02](F08-T02.md), [F08-T03](F08-T03.md) |
| [F09-T02 — Cross-journey usability](F09-T02.md) | planned | Employees, managers, HR and SLT can complete coherent accessible review/development journeys. | [F09-T01](F09-T01.md) |
| [F09-T03 — Release verification](F09-T03.md) | planned | An integrated candidate has evidence for regressions, permissions, generated-input properties, MySQL, build and representative performance. | [F09-T02](F09-T02.md) |
| [F10-T01 — Hosting preflight](F10-T01.md) | planned | Determine whether the actual shared 20i package can safely run the prepared Laravel release. | [F00-T02](F00-T02.md) |
| [F10-T02 — Staging and recovery](F10-T02.md) | planned | A prepared release runs on private staging and can be backed up/restored with demonstrable recovery. | [F10-T01](F10-T01.md), [F09-T03](F09-T03.md) |
| [F10-T03 — Pilot-readiness pack](F10-T03.md) | planned | Johan and HR have concrete operational, data/framework, support and rollout criteria for a representative pilot. | [F10-T02](F10-T02.md) |

## Feature review and acceptance register

| Feature | State | Review/acceptance evidence |
| --- | --- | --- |
| F00 — Foundation | Technical review verified | Independent agent review on 16 October 2026 found P1 quoted identities and P2 inherited service defaults; [C04](F00-C04.md) and [C05](F00-C05.md) record regressions, fixes and clean scoped re-review. Final SQLite 67 / 475; MySQL 70 / 483; fuzz 3 / 402; Pint, analysis and build passed. Proceed technically under Johan’s autonomous instruction; no human/business acceptance is inferred. |
| F01 — Identity and organisation | Technical review verified | Local logins, directory and scopes verified. Independent review identified two P2 directory issues; [C01](F01-C01.md)/[C02](F01-C02.md) retain regressions/fixes and clean re-review. SQLite 116 / 862, isolated MySQL 121 / 882, analysis/Pint/build passed. Microsoft deferred. Proceed technically under the autonomous instruction; no business/live acceptance is inferred. |
| F02 — Skills and assessment | Technical review verified | Independent working-tree review found two P2 issues, resolved by [C01](F02-C01.md)/[C02](F02-C02.md), plus the related directory [F01-C03](F01-C03.md). Clean scoped re-review independently passed 44 / 276. Final SQLite 160 / 1,138; isolated MySQL 172 / 1,225; analysis/Pint/build and fictional browser checks passed. Proceed technically; HR/live acceptance remains separate. |
| F03 — Frameworks and scoring | Technical review verified | Independent working-tree review and [C01](F03-C01.md)/[C02](F03-C02.md)/[C03](F03-C03.md) corrections verified; clean scoped re-review independently passed 29 / 160. Final SQLite 190 / 2,098; isolated MySQL 202 / 2,185; fuzz 5 / 1,302; analysis/Pint/build and illustrative browser checks passed. Technical progression only; no real HR/live policy acceptance. |
| F04 — Pay and operational context | Technical review verified | Independent working-tree review identified [C01](F04-C01.md), reproduced/fixed with clean re-review and independent 34 / 177. Final SQLite 224 / 2,275; isolated MySQL 236 / 2,362; analysis/Pint/build and fictional browser checks passed. Technical progression only; HR/live pay and metric policy remain external inputs. |
| F05 — PDR workflow | Technical review verified | Independent working-tree review and C01–C06 corrections are clean; independent focused 42 / 351. Final SQLite 266 / 2,626; MySQL 285 / 2,754; fuzz 5 / 1,302; analysis/Pint/build/diff check pass. Fictional browser draft/submission/HR journeys verified; privileged browser walkthrough awaits preview-only grants. Proceed technically under the autonomous instruction; HR/live policy acceptance and hosting remain separate. |
| F06 — Development and history | Planned | Not reviewed or accepted. |
| F07 — Imports | Planned | Not reviewed or accepted. |
| F08 — Outputs | Planned | Not reviewed or accepted. |
| F09 — Demonstration and release checks | Planned | Not reviewed or accepted. |
| F10 — Staging and pilot readiness | Planned | Not reviewed or accepted. |

Record the review chat/date, reviewed commit or exact working-tree scope, findings, correction-task links, verification and Johan's feature acceptance here. Do not infer acceptance from a passing suite or a commit.

Correction IDs use the owning feature, for example `F02-C01`; create a task contract and add it to this index. A correction must not quietly expand feature scope.

## Implementation-chat starter

```text
Implement task F01-T01 from docs/tasks/README.md, after F00 acceptance.

Read AGENTS.md, the task, relevant specification sections,
prerequisite contracts and the actual repository state.
Implement only this task. Follow specification-driven development,
Laravel conventions, thin controllers and no SPA navigation.
Run the required checks and give me exact terminal commands
and expected results for my own test run.
Update task evidence and the index with actual results.
Finish with changes, verification, limitations and next-task handoff.
Leave staging and commits to me.
```

Replace the task ID for each new chat. If a task exceeds a manageable chat, split it and update contracts/dependencies before proceeding.

## Feature-review chat starter

```text
Review feature F00 against AGENTS.md, docs/specification.md,
the task contracts, actual code and verification evidence.

Review only; do not implement or commit changes.
Check behaviour, permission boundaries, data integrity, failure cases,
relevant tests and compatibility with the shared-hosting/no-SPA rules.
Report actionable findings with severity and file/line evidence.
Separate demonstrated defects from missing verification or decisions.
Suggest bounded correction tasks; do not mark the feature accepted.
```

Replace the feature ID. Resolve material findings and obtain feature acceptance before building dependent features.

## Verification convention

Every task records **agent-run** and **user-run** evidence separately with date, exact command/scenario, result and relevant limitations. Tests requested from Johan remain pending until his output is supplied.

F00-T02 established `composer analyse`, `composer test:fuzz` and `composer test:mysql`, plus seed replay/shrinking and safe test-database configuration. Exact commands, local setup and expected results are in [the quality guide](../quality.md).

After focused checks Johan runs `php artisan test --compact`; add `npm run build` when appropriate. Exact focused test paths in planned task contracts are intended deliverables, not claims that files or tests exist now. Update contracts if actual naming changes, and never count an empty filter as a pass.

Baseline-only commands and expectations are in [baseline](../baseline.md). No destructive command may target the supplied connected development database. Use isolated MySQL for real engine constraints, locking and concurrency.

## Milestones

1. Trusted foundation: F00–F01 accepted.
2. Working assessment/review platform: core features and data exchange accepted.
3. Local demonstration ready: F09 accepted.
4. Pilot ready: F10 accepted with HR-approved real data/frameworks.
5. Live pilot/wider rollout: use agreed acceptance criteria and bounded follow-up tasks.

## Immediate handoff

Implement F06-T01–T02 sequentially with agent-run checks and independent feature review. Microsoft sign-in (F01-T02) is deferred by Johan’s local-login-only direction. Preserve shared-checkout changes and all database safeguards. Keep recording results per task; do not stop for routine user verification or commit requests.
