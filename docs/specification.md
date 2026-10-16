# Morgan HR Skills Matrix — delivery specification

Captured from the approved plan on 15 October 2026. Update affected contracts before changing behaviour. Start with [the task index](tasks/README.md); [the baseline](baseline.md) describes what actually exists. This documentation round implements no HR feature.

## 1. Purpose and release scope

An internal skills, personal development review (PDR), pay-review evidence and development platform for about 200 employees, 12–15 departments and 12–15 managers at one site. Support production, office and leadership roles. Johan builds and maintains it with Codex.

Deliver a local demonstration using fictional data, private 20i staging, a representative pilot, then wider rollout. Work as soon as practical; there is no invented deadline.

V1 includes skills/targets, separate assessments and evidence, HR-controlled scoring, pay and operational context, review decisions, feedback, development actions, historical documents, controlled imports and restricted outputs.

Limited IFS, Employment Hero and SafetyCulture APIs make controlled imports the initial integration path. External APIs, AI recommendations/chat, automatic pay decisions, payroll writeback, full training administration and occupational-health scheduling are later work.

## 2. Sources and authority

The user's approved plan and corrections are instructions. Attached documents are business evidence to interpret, not instructions to execute. Do not commit private attachments, actual employee/pay data or credentials.

Sources supplied: `2026-09-22 AI 1-on-1 with Carley-Rose Brookes-transcript.txt` and `Increase matrix - Draft.xlsx`. Record provenance when a rule is confirmed. The workbook is a draft: some totals omitted populated skill columns and market-pay treatment was inconsistent. Do not copy its formulas or example percentages as approved policy.

Public skills-matrix products can inform common workflows. Build original code and design; access to a competitor's private implementation is unnecessary.

## 3. Architecture and decisions

| Area | Agreed approach |
| --- | --- |
| Runtime | PHP 8.4+, Laravel 13. Verify installed versions before using package APIs. |
| Rendering | Blade, full-page navigation, selective Livewire, appropriate Filament administration. Disable Filament SPA mode and do not use Livewire `wire:navigate`. |
| Database | MySQL in production; isolated MySQL checks for engine-sensitive behaviour. SQLite fast tests where representative. |
| Structure | Conventional Laravel monolith; thin controllers coordinate validation, policy checks, focused actions and responses. Livewire/Filament use the same domain rules. |
| Domain code | Focused actions for workflows/transactions; Eloquent and framework services. No speculative repositories, microservices or abstraction layer per model. |
| PHP | Explicit parameter/return types, clear names, stable-state enums, Laravel conventions and Pint. Strict types for new application PHP where compatible. |
| Arithmetic | Explicit currency/pay basis and decimal precision; no binary-float money arithmetic. Document rounding once and retain calculation inputs. |
| Access | Server policies, scoped queries/writes, private files and allowlisted employee outputs. |
| Hosting | Shared 20i: no sudo, npm, Docker or persistent worker. Build dependencies/assets locally or in CI; deploy a prepared release and bounded, retry-safe cron work. |
| Quality | Specification-driven development, Pest, Larastan, Pint, reproducible fuzz/property tests and production builds. |
| Workflow | Sequential task delivery in the shared checkout; Johan's 16 October 2026 instruction authorises continuous agent implementation and verification. Johan handles commits. See AGENTS.md. |

Install missing packages in the task that needs them after compatibility checks. Preserve the base and Pest 5. F00-T02 defines exact quality commands and the fuzz implementation; no unverified plugin is prescribed.

## 4. Organisation and identity

Employees have stable organisation identifiers independent of names/emails. Accounts link to people. Maintain department, role, active status and reporting manager; reject self-reporting/cycles and preserve records referenced by history.

Provision accounts explicitly; no public registration. Office/some production employees have Microsoft identities, while most production employees need native accounts without email. HR/designated administrators verify identity and issue private, expiring, single-use setup/reset access. Audit issuance/use without logging usable tokens.

Microsoft sign-in is deferred by Johan’s 16 October 2026 local-login-only direction; it does not gate the local system. If reintroduced, Microsoft sign-in is tenant-restricted and links explicitly provisioned users through stable provider/tenant identifiers. Never merge solely on matching email or silently provision unknown users. Disabled accounts lose access through every sign-in path. Document controlled initial HR-account provisioning.

## 5. Permissions and information boundaries

| Actor/capability | Scope |
| --- | --- |
| Employee | Own permitted profile, self-assessments, assigned review sections, released outcomes and development. |
| Manager | Authorised direct/indirect reporting descendants, manager assessments and allocated reviews. |
| HR | Organisation/framework/review administration; confidential data still requires its capability. |
| Designated SLT | Explicit organisation review/decision responsibilities with separate pay permissions. |
| Account administrator | Provisioning/recovery authority does not itself confer salary or review-decision access. |

Department membership alone grants no broad access. Separate actual-pay, benchmark, recommendation and approval capabilities. Manager tree access does not automatically grant all salary access. Prohibit self-approval.

Apply current scope/capability checks to pages, queries, direct identifiers, Livewire actions, Filament, attachments, PDFs and exports. Historical participants remain in snapshots, but access is checked at retrieval time. Revoke disabled/changed access promptly across existing sessions.

Audit sensitive administration, recovery, framework/assessment changes, submission/returns/decisions/release, confidential downloads and exports. Include actor/time/record/provenance without secrets or unnecessary document contents. Employee-safe content must be constructed on the server before rendering or Livewire hydration.

## 6. Catalogue, targets and assessments

HR manages grouped skills and rating definitions for production, office and leadership. Retire referenced definitions instead of deleting history.

An explicit HR-approved individual target exception wins; otherwise use the higher applicable role/department target. Show the source. No target, unassessed, permitted N/A and assessed zero are distinct.

Keep separate employee and manager ratings, comments, dated evidence and history. Never overwrite the self-rating or average the two into a formal rating. The manager's evidenced assessment drives formal scoring. Leadership gaps support development.

Self assessments are visible to their author and current authorised reporting managers/scoped HR review administrators. Raw manager assessment comments/evidence remain confidential from the employee until the explicit approved employee-safe review release. SLT decision access is to the submitted review workflow, not an implicit raw-draft assessment grant. Technical evidence defaults are PDF/PNG/JPEG up to 10 MiB, private attachment downloads and immutable versions/digests; HR confirms live limits and retention before activation.

The accessible matrix displays permitted people, grouped skills, ratings, resolved targets, gaps and coverage. Preserve filters/pagination and identifying context. Missing information must not look like a zero rating; provide text as well as colour.

## 7. Versioned frameworks and explainable scoring

HR drafts/validates/publishes frameworks with ordered rating levels, explicit points, positive component weights, evidence rules and bands. Published versions are immutable; edits create a successor.

Average applicable assessed rating points per component; apply approved weights normalised over included valid components. Display counts, exclusions, denominator, effective weights, version, evidence completeness and calculation breakdown. Required missing evidence/ratings block submission. Permitted exclusions/N/A remain visible and never become zero.

Before F03 verification, define decimal precision, rounding stage/mode, inclusive band edges, applicability and all-excluded behaviour in this specification and executable examples. Incomplete drafts must not imply a final score. Approved points must be monotonic; scoring is deterministic, order-independent, bounded and cannot decrease when one eligible rating increases under unchanged rules.

### F03 numerical and publication contract

- Each framework version stores a snapshot of selected skill groups/components, names, applicability, rating meanings/points, required-rating/evidence rules, positive integer relative weights and score bands. A skill appears once, in its catalogue group. At least one component/skill and two ordered active levels per skill are required to publish. Rating points are strictly increasing with ordinal, within 0.00–100.00. Published versions cannot be edited/deleted; a successor has its own stable ID and predecessor link. Draft and publish writes reject stale revision numbers.
- Points and band edges accept at most two decimal places and are stored as integer hundredths, avoiding binary decimal input ambiguity. Weights are relative integer units from 1 to 10,000. The calculator averages included rated points within each component, then normalises the positive weights over components with at least one included rating. It calculates in a fixed skill/component order using double precision without intermediate rounding, then rounds only the final percentage to two decimal places using half-up. Band selection uses that displayed rounded score. Explicit boundary examples cover rounding.
- Bands are ordered by lower edge and must cover 0.00–100.00 without gaps or overlaps. A lower edge is inclusive; an upper edge is exclusive, except 100.00 is included in the final band. A score of exactly 50.00 therefore belongs to a band beginning at 50.00.
- Applicability uses the published department/role snapshot: neither list means global; either matching list includes the employee. An active individual target exception can include a selected skill outside those lists. An invalidated exception remains applicable and blocks completion for HR attention; it is never silently removed. The later submitted review freezes employee assignment, target resolution and revision inputs. Subsequent catalogue/organisation changes cannot rewrite a frozen review.
- Only independent manager assessments enter formal scoring. Self ratings remain separate. Explicit permitted N/A with a reason is excluded from the denominator; unassessed/missing ratings are displayed separately and never become zero. Required missing ratings/evidence block final submission. Optional missing inputs may be excluded, with counts and effective weights disclosed. A rated zero remains included. All-excluded/no-rated input produces no score or band and blocks final submission. Components with no included ratings have effective weight zero.
- Evidence is complete when the selected manager revision has substantive evidence notes or a readable attached file with matching recorded size and SHA-256; required N/A needs a reason. Required evidence is checked for rated entries. A preview with blocking reasons is visibly provisional and cannot claim a final result. Evidence and revisions are snapshotted by downstream review submission.
- Worked examples are illustrative technical fixtures until HR approves real policy: component means 25 and 75 with weights 1:3 yield 62.50; a real zero and 100 average to 50.00; N/A plus 100 yields 100.00 with one exclusion; all excluded yields no score. Swapping input order preserves the result.
- Illustrative publication is labelled throughout. Non-illustrative publication requires an explicit HR approval reference entered by an account with ManageFrameworks; software verification does not supply that approval. No actual live version is published by agent checks.

Real rating points, weights, bands and evidence requirements need HR approval. Demonstration frameworks are visibly illustrative and cannot silently become live policy.

Market/pay position, attendance, PPE, quality/right-first-time, rework, retention/replacement difficulty and role criticality are human-decision context outside the skills score.

## 8. Pay, benchmarks and operational context

Actual pay with effective dates is separate from proposals/approved changes. Record currency, hourly/annual basis and explicit hours/weeks assumptions. Compare only compatible records with visible assumptions; unknown/incompatible units yield an explanation rather than a guess.

### F04 pay comparison contract

Pay and benchmark entries are append-only effective-dated records. Corrections explicitly identify their predecessor and reason; they preserve the old evidence. Per-person pay sequence and benchmark correction checks reject stale concurrent submissions. Effective pay is the latest unsuperseded record on/before the selected date, never a future record. Proposals and approvals never overwrite actual pay.

Amounts accept positive decimal values with at most two decimal places and store integer minor units. Supported entry currencies are GBP, EUR, USD and ZAR; no foreign-exchange conversion is inferred. Pay basis is explicitly annual or hourly. Weekly hours and weeks/year are supplied together when a conversion needs them, at up to two decimal places (hours >0 and <=168; weeks >0 and <=53). These are technical input bounds, not HR-approved contractual assumptions. No default 37.5/40-hour or 52-week assumption is inserted.

Comparison uses the actual record’s basis. A same-basis benchmark uses its recorded amount. Annual-to-hourly divides the benchmark amount by its own stated hours × weeks; hourly-to-annual multiplies by those explicit benchmark assumptions. Round only the converted amount to the nearest minor unit, half-up; show the source basis, hours/weeks and formula. Show absolute and percentage position against the benchmark, with no proposed increase or payroll change. Missing hours, different currencies/job roles, invalid/expired benchmark periods or no effective actual record produce an explicit non-comparable reason. Provenance and duty/company limitations stay visible.

Actual pay requires the separate ActualPay capability plus current person scope. Benchmark maintenance requires Benchmarks. A comparison requires both. Directory/account/framework/review permissions do not imply either. No pay fields enter ordinary employee, matrix or scoring projections. All agent fixture examples remain fictional; live basis/currency/working-time assumptions require HR confirmation.

HR-curated/imported benchmarks need source, dates, duties, role/company context and comparability limitations. No market scraping or automatic increase/budget allocator is required.

### F04 operational context contract

Operational records are append-only, source-dated evidence scoped to an employee. Attendance absence hours, PPE observation counts, right-first-time quality percentage and rework hours are factual metrics; retention/replacement, role criticality and other human context are labelled interpretations. Metric types have fixed explicit units (hours, observations, percent or narrative). Numeric values are non-negative decimal hundredths; observation counts are whole numbers and percentages are bounded 0–100. No missing value or missing period is converted to zero. Narrative-only kinds require substantive text.

Each record requires an inclusive start/end period and source/reference; the recorder comes from the current authorised account. Corrections preserve the original row and reason, and a per-person sequence detects stale writes. Current same-kind overlapping periods are displayed as warnings and are never automatically summed. A selected viewing period with no evidence explicitly states missing evidence; a partly overlapping record is labelled with its full source period. Incompatible units and incomplete periods are validation errors.

All operational records and narratives initially require the separate SensitiveContext capability plus current person scope for read/write. This conservative technical boundary does not grant an ordinary manager access merely through their reporting role. HR confirms metric policy/periods and any finer visibility before live use. No metric or interpretation enters ScoreCalculator. F05 freezes permitted context in its internal review evidence while employee release uses an explicit safe allowlist.

Operational facts carry source, period, units, recorder and corrections; missing periods remain missing. Distinguish facts from interpretation. Restrict pay and sensitive narrative through capability checks.

## 9. Review lifecycle and immutable revisions

Visible flow: draft → submitted → HR reviewed → SLT approved → feedback recorded → employee acknowledgement. Keep score, manager proposal and human final pay decision distinct.

### F05 cycle, allocation and draft contract

A review cycle selects one published immutable scoring framework and explicit opening/closing dates. HR review administration plus current person scope controls allocation. Each cycle/person has one stable review head, an explicitly assigned active manager account and an explicit production/office/leadership section profile. Profile prompts are snapshotted at allocation; they are illustrative technical templates until HR approves live wording. Non-illustrative cycles require a template approval reference. An assigned reviewer must currently supervise the person; allocation does not widen person scope or permit self-review. HR can reassign a draft with a reason and stale-token protection.

The employee owns reflection, achievement examples and development needs. The assigned current manager owns a separate summary, role-specific observations and structured objectives (description, success measure, due date). Employees never receive manager sections, proposals, actual pay, benchmarks or restricted context in their draft response. Review administrators may inspect scoped drafts but cannot silently impersonate the employee/assigned manager to edit their sections. A pay proposal requires RecommendPay; reading actual pay and choosing benchmark evidence retain their independent capabilities. No-change/proposed absolute pay are explicit human proposals with reasons, not calculated recommendations.

Draft content uses optimistic revision numbers and server-side section allowlists. Saving changes only the authorised section. Old input stays paired with its old token after rejection until explicit reload. Vanilla same-origin autosave serialises in-flight writes and shows Saving/Saved/Failed status without page navigation; failed/stale/network requests preserve local fields and require deliberate recovery. Ordinary POST remains available. Drafts outside the cycle window or locked by submission cannot be edited. All previews are provisional.

Later submission revalidates required employee reflection when selected by HR, manager summary, at least one complete objective and the framework scoring/evidence rules. Immutable submissions retain the exact draft, participants/prompts, framework, assessment/target revisions, calculation, restricted source context and private evidence copies. Internal views must project these snapshots according to current capabilities; employees see only their own draft section until explicit approved release.

Allocate cycles/participants and role-appropriate sections, objectives/development needs and pay proposals. Drafts autosave with clear Saving/Saved/Failed status, preserve input on error and detect stale writes. Submission revalidates completeness server-side.

A submission freezes ratings, targets, participants, evidence references/versions, framework, calculation inputs/output, salary/benchmark and operational context. Protect referenced evidence against later mutation/loss. Subsequent edits to live records cannot alter the revision.

HR and SLT may return with reasons. A return creates an editable successor; resubmission produces a new immutable revision while preserving previous submissions/decisions. Enforce transition order, no self-approval and transactional concurrency/duplicate protection.

After approval record the feedback conversation and explicitly release an employee-safe summary, including the communicated final pay outcome, goals and development. Exclude internal comparisons, other people's data, confidential proposals/calibration. PDFs and Livewire payloads obey the same allowlist.

Acknowledgement means receipt, not agreement; employees may comment/disagree. It never edits the approved evidence. Approval does not overwrite actual pay or update payroll automatically.

## 10. Development, documents, imports and exports

Development actions connect gaps/objectives to owner, due date, progress, resource/course links and completion evidence, including leadership development. This is not a full LMS.

Historical PDRs are private attachments with explicit visibility and audited authorised downloads. Validate names/types/sizes and store outside the web root. Uploading history does not create a structured current score. Technical defaults use the existing PDF/JPEG/PNG 10 MiB limit. HR-only files require scoped review administration plus actual-pay and sensitive-context capabilities. Sharing with current supervisors or employee/current supervisors is an explicit file-wide content-review choice; visibility changes are immutable audited revisions with optimistic concurrency. Metadata and original file are immutable; no automatic retention/purge policy is assumed before HR approval.

CSV/XLSX imports require mappings, preview, validation, stable employee identifiers, explicit conflict choices, provenance, source periods and units. Recheck scope/constraints at apply time and apply the approved batch transactionally. Repeated batches must reconcile/no-op explicitly, avoiding duplicates. Do not execute workbook formulas as application instructions.

Provide employee-safe released-review PDFs, scoped matrix/report exports and approved payroll-change CSV with stable IDs/audit history. Protect spreadsheet text against formula execution. Export does not change payroll or actual-pay records.

## 11. User experience

UK English, restrained readable screens and clear next actions. Johan’s 16 October 2026 visual direction sets a responsive 1440 px (90 rem) shared shell, light-only browser/app surfaces and Tailwind v4 utilities. Header, main content and footer share the same width token; individual forms and prose retain readable measures. Preserve Instrument Sans, emerald/slate colours, current routes, permissions and task behaviour. Remove embedded starter CSS and automatic dark-theme variants; generated Tailwind assets and font configuration remain in the Vite build. Full-page navigation preserves orientation. Matrices use grouped skills, useful filters, manageable pagination and sticky identifying context where useful.

Use accessible labels, inline rating definitions/evidence guidance, linked completeness errors, keyboard/mobile support, explicit save/conflict recovery and source dates/completeness. Never rely on colour/motion alone.

Visual refinement on 16 October 2026 used a manual guidance pass from [Impeccable](https://github.com/pbakaus/impeccable), [UI UX Pro Max](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill), [Taste](https://github.com/leonxlnx/taste-skill) and [Hallmark](https://github.com/Nutlope/hallmark). No design CLI, hooks or app dependencies were installed. Shared navigation uses a native keyboard-operable Menu disclosure below the wide-screen breakpoint, with unchanged permission-checked destinations. Focus/error/pressed states and tabular data numerals use Tailwind. Agent verification: full SQLite **306 tests / 2,838 assertions**, production build, Pint and whitespace check passed. Browser measurements confirmed the three shared shell widths are 1440 px at a 1600 px viewport, root colour scheme is light only, and login/matrix fit 320, 375, 414 and 768 px without page-level horizontal overflow; the matrix keeps its own deliberate horizontal scrolling. Keyboard validation and menu interaction were exercised. Independent source review found no material regression; this is not a formal WCAG certification or HR acceptance.

### Proposed screen references

The [visual design gallery](visual-design.md) contains twelve full-length proposed screens, an overview and the original 3,840 px wide presentation images. These use fictional demonstration data and illustrate the proposed interface; the written contracts define behaviour and approval boundaries. The separate 1,920 px / 1,440 px content editable Figma conversion is deferred.

## 12. Verification and deployment gates

Each task proves normal behaviour, access restrictions and material failures. Agent focused checks and Johan's terminal runs are separate evidence. Give exact commands/expected outcomes each round; a pending user run is not a pass.

F00-T02 establishes tooling; F03-T02 proves scoring examples/properties. Apply generated inputs to parsers, imports, graphs/transitions where valuable. Preserve seeds/settings, useful shrinking results and minimal regression cases. Fuzz tests complement explicit examples.

Use explicitly isolated test databases; never reset the supplied connected development database. Before MySQL setup, require an independently known literal development database identity from .env (DB_DATABASE or the decoded path of DB_URL); refuse variable-derived, missing or blank identities rather than comparing unresolved text or using the PHPUnit-overridden process environment. Capture inherited DB_DATABASE/DB_URL presence before Laravel loads dotenv, without first overwriting these variables in the test profiles; refuse MySQL when inherited identity overrides exist or capture is unavailable. The profile exposes only its fixed, validated test connection before providers; other named SQL connections are unavailable. Reject development names case-insensitively equal to the test schema, including decoded URL paths. Refuse remaining quote characters whose Laravel normalisation could obscure identity. Install fixed non-external test service defaults before providers, independent of inherited selectors. In-memory SQLite does not require a known development identity and remains fixed to :memory:. F09 verifies complete journeys, bypass attempts, concurrency, builds, privacy and representative performance.

F10 checks the actual hosting PHP/extensions, MySQL, CLI/cron, web root/private paths, HTTPS, secrets, prepared deployment, bounded jobs, backups and demonstrated restoration. Suitability remains conditional on that evidence.

## 13. External decisions and readiness

These inputs block their dependent behaviour/live activation, not unrelated foundation work:

- Microsoft tenant/app configuration and identity administrators: F01-T02.
- Real stable identifiers, roster/reporting lines and salary capabilities: F01/F07 and pilot preparation.
- HR-approved targets, rating points/weights/bands/evidence rules and pay conversion assumptions: F02–F05 before live use.
- Review participants/dates, employee release wording, document retention/visibility and decision makers: relevant features before acceptance/live use.
- Actual 20i access/capabilities, private staging and backups: F10.
- Pilot participants, rollout/support acceptance: F10-T03.

Use synthetic fixtures meanwhile. Record each decision, owner and effect in the task evidence; do not silently invent business policy.

### F05 immutable submission and HR transition implementation contract

Submission uses current head/draft tokens and the assigned current supervisor. It freezes schema-versioned participant/cycle/template, published framework, score inputs/breakdown, assessments/targets and separately restricted pay/benchmark/context facts. Employee reflection is required when configured; manager summary and at least one complete measured dated objective plus complete scoring are always required. Intact attachments are copied privately and hash checked; unavailable optional attachments are labelled. Return preserves the submitted draft and snapshot and starts a distinct successor draft with lineage. Current scoped HR authority may review/return, excluding the subject and assigned manager. Every transition references the current immutable submission and records actor/time/reason; duplicate/stale transitions cannot both succeed. Employee access to internal snapshots remains denied until the separate release contract.

### F05 SLT decision implementation contract

A current scoped SLT account needs reviews.decide, pay.approve and pay.actual together. Subjects and assigned submitting managers cannot decide their own revision. Decisions reference exactly the current HR-reviewed immutable submission and require the current head version. Each submission has one immutable human approve/return decision with reason. Approved change has explicit positive amount/currency/basis/effective date; no change has no guessed terms. Return creates a distinct successor draft with reason and lineage. Approval does not mutate actual-pay history or payroll.

### F05 feedback, release and receipt implementation contract

Feedback is a dated immutable employee-intended summary/development revision against the approved submission. The assigned current supervisor or current scoped HR review administrator may record and explicitly release it; HR substitution needs a recorder reason, and self-subject actions are denied. Corrections before release append feedback revisions. Release freezes a server-generated allowlist of stable subject/cycle/framework labels, approved score/band, employee reflection, goals, intended feedback and approved human final pay outcome. No internal proposal/calibration, pay history, benchmarks, operational context, evidence paths or other people enter that summary. The employee sees their released summary; staff need current person access and ActualPay for its full pay-containing version. Release and employee receipt require current head versions. Receipt or disagreement is immutable and distinct from consent, never a change to approved evidence or payroll.

### F06 development action implementation contract

Development actions belong to one employee and retain an immutable origin label/reference from an applicable skill/permitted assessment, released same-person review objective, or explicit manual/leadership plan. Current supervisors/scoped HR administer plans; employees record intended progress on their own actions. Every change appends a revision with an optimistic head token. Plans have ordered start/due dates and optional labelled HTTP/HTTPS resources without embedded credentials. Completion needs dated notes or intact private evidence; cancellation/reopening terminal work is a reasoned supervisor action. Private completion files are validated PDF/JPEG/PNG up to 10 MiB, hash checked and downloaded only through current scope with audit. Employee-facing origin state excludes raw manager ratings/notes and restricted review/pay context.
