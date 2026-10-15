# Delivery task index

Current round: **F00-T02 — verified and committed** at `85ef8e8`. User-run checks passed on 15 October 2026 and are recorded in the task. Next: a fresh F00 review chat; feature F00 is not yet accepted.

Read [project instructions](../../AGENTS.md), [the specification](../specification.md), [baseline evidence](../baseline.md) and the assigned task. [The template](TEMPLATE.md) defines the eight required contract sections. [Skills guidance](../skills.md) preserves requested sources without requiring every skill in every chat.

## Delivery rules and states

One independently verifiable behaviour per implementation chat, across the database/application/UI/tests needed for that behaviour. Keep work sequential in the shared checkout. Johan opens chats and stages/commits changes.

- **planned:** not yet eligible or not yet selected.
- **ready:** prerequisites and necessary external inputs/feature gates are satisfied.
- **in progress:** the one task currently being implemented.
- **verified:** actual checks support completion and it is ready for Johan's review/commit.
- **blocked:** a specific prerequisite/input is missing; record what and which work remains possible.

Task verification does not equal feature acceptance. At a feature boundary use a fresh, read-only review chat, resolve material findings through correction tasks, then record acceptance before dependent feature work begins. Internal task dependencies are the exact implementation prerequisites. The feature gate is an additional quality gate.

Small synthetic fixtures can accompany earlier tasks; F09-T01 is the final integrated demonstration dataset. Run F10-T01 early after F00 acceptance when hosting access is available; it need not wait for F09.

## Task backlog

| Task | State | Complete behaviour | Prerequisites |
| --- | --- | --- | --- |
| [F00-T01 — Baseline and specification](F00-T01.md) | verified | Establish a trustworthy baseline and repository records that a fresh chat can use without conversation history. | Supplied base and repository |
| [F00-T02 — Quality checks](F00-T02.md) | verified | Provide repeatable quality commands for PHP, tests, generated inputs, isolated MySQL and production assets. | [F00-T01](F00-T01.md) |
| [F01-T01 — Platform accounts](F01-T01.md) | planned | Provisioned employees can sign in/out, including employees without email, using private HR-issued setup/recovery access. | [F00-T02](F00-T02.md) |
| [F01-T02 — Microsoft sign-in](F01-T02.md) | planned | Explicitly provisioned users can sign in with the approved Microsoft tenant identity. | [F01-T01](F01-T01.md) |
| [F01-T03 — Organisation directory](F01-T03.md) | planned | Authorised HR manages employees, departments, roles, reporting lines and account links. | [F01-T01](F01-T01.md) |
| [F01-T04 — Scoped permissions](F01-T04.md) | planned | Employees, managers, HR and SLT see and change only permitted people and fields across every entry point. | [F01-T02](F01-T02.md), [F01-T03](F01-T03.md) |
| [F02-T01 — Skills catalogue](F02-T01.md) | planned | HR maintains grouped skills and rating definitions for production, office and leadership roles. | [F01-T04](F01-T04.md) |
| [F02-T02 — Targets](F02-T02.md) | planned | Users see a consistently resolved target and its source for each applicable person/skill. | [F02-T01](F02-T01.md) |
| [F02-T03 — Assessments and evidence](F02-T03.md) | planned | Employees and managers record independent dated assessments with comments/evidence and history. | [F02-T02](F02-T02.md) |
| [F02-T04 — Skills matrix](F02-T04.md) | planned | Authorised users inspect filtered ratings, target gaps and skill coverage in an accessible matrix. | [F02-T03](F02-T03.md) |
| [F03-T01 — Framework publishing](F03-T01.md) | planned | HR safely drafts and publishes immutable versions of the scoring framework. | [F02-T01](F02-T01.md) |
| [F03-T02 — Explainable scoring](F03-T02.md) | planned | Users see an approved weighted score with an auditable breakdown and explicit completeness. | [F03-T01](F03-T01.md), [F02-T03](F02-T03.md) |
| [F04-T01 — Pay and benchmarks](F04-T01.md) | planned | Authorised users maintain actual pay and sourced benchmarks with honest annual/hourly comparisons. | [F01-T04](F01-T04.md) |
| [F04-T02 — Operational context](F04-T02.md) | planned | Authorised users record source-dated operational evidence and human context separately from scoring. | [F01-T04](F01-T04.md) |
| [F05-T01 — Guided PDR drafts](F05-T01.md) | planned | Allocated employees/managers complete role-appropriate PDR drafts with objectives and pay proposals. | [F03-T02](F03-T02.md), [F04-T01](F04-T01.md) |
| [F05-T02 — Submission and HR review](F05-T02.md) | planned | Submission freezes a review revision; HR reviews or returns it with reasons and preserves resubmission history. | [F05-T01](F05-T01.md), [F04-T02](F04-T02.md) |
| [F05-T03 — SLT decisions](F05-T03.md) | planned | Authorised SLT compares HR-reviewed submissions and approves or returns recommendations safely. | [F05-T02](F05-T02.md) |
| [F05-T04 — Feedback and acknowledgement](F05-T04.md) | planned | Managers record feedback, release a safe approved outcome and employees acknowledge receipt or disagree. | [F05-T03](F05-T03.md) |
| [F06-T01 — Development actions](F06-T01.md) | planned | Identified gaps/objectives become owned, dated development actions with progress and completion evidence. | [F02-T03](F02-T03.md), [F05-T01](F05-T01.md) |
| [F06-T02 — Historical documents](F06-T02.md) | planned | Authorised users upload and retrieve historical PDRs with explicit visibility and audited access. | [F02-T03](F02-T03.md) |
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
| F00 — Foundation | Awaiting review | F00-T01/F00-T02 verified; F00-T02 implementation committed at `85ef8e8`, user checks passed. Independent review and acceptance outstanding. |
| F01 — Identity and organisation | Planned | Not reviewed or accepted. |
| F02 — Skills and assessment | Planned | Not reviewed or accepted. |
| F03 — Frameworks and scoring | Planned | Not reviewed or accepted. |
| F04 — Pay and operational context | Planned | Not reviewed or accepted. |
| F05 — PDR workflow | Planned | Not reviewed or accepted. |
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

F00-T01 and F00-T02 are verified; F00-T02 implementation is committed at `85ef8e8` and both tasks have user results recorded. Open a fresh read-only F00 review chat against that commit plus the current documentation-only evidence updates. Resolve material correction tasks and record F00 acceptance before F01-T01. Johan confirmed repository tracking for documentation. The /docs ignore rule has been removed; include .gitignore and all 38 documentation records in the next user-owned commit so fresh chats and clones retain the specification, tasks and evidence. No application retest is needed for these text-only changes. Hosting preflight may also run after F00 acceptance when access is available.
