# Group keys that send for review, and a queue scoped by separate groups

Status: **decided 2026-11-05.** The owner accepted every recommendation (R1-R8, S1-S9, T1-T4)
as written. **Delivered 2026-11-06**, every stage merged: R1 #122, R2 #123, S1 #124, S2 #125, T0 #126,
V1 #127, V2 #128, V5 #129, V3 #130, V4 #131 (V6, the decision history read from the log, stays optional).
Open for the owner: a narrowed moderator approving with the pre-ticked key route drops the method's
groups outside their scope (see #130), and the README screenshots need recapturing. Builds on [`group-enrolment-key.md`](group-enrolment-key.md) (G1-G15, K0-K8) and
[`group-key-only.md`](group-key-only.md) (D1-D8, Q0-Q4), whose decisions stand unless this file
says otherwise. Every `file:line` is at `origin/master` a9a49f6.

## The request

The owner's words, kept verbatim:

> Plugin moodle-enrol_apply, tem permissão específica para usuário apenas aprovar/reprovar pedidos
> de inscrição, respeitando configuração de grupos separados do curso? A expectativa é dar
> permissão para um moderador no curso, com grupos separados, cuidar das solicitações de inscrição
> do seu grupo.

> Vamos seguir evoluindo a ideia. Primeiro uma nova funcionalidade, também relacionada a grupos.
> Hoje temos o recurso "O que a chave de inscrição do grupo faz" com as seguintes opções: [...]
> Vamos adicionar a quarta opção:
> * Só com chave: uma chave válida envia a inscrição para análise
>
> Depois implementar as regras para grupos separados, com as permissões granulares.
>
> Em seguida vamos rever todas as telas de aprovação, análise e logs de auditoria para melhorias e
> filtros por grupos.

Three stages, in that order. They are coupled: the fourth mode is what gives every pending
application a group, and a group is what a separate-groups moderator needs to be scoped by.

## What is true today

- `enrol/apply:manageapplications` (`db/access.php:55`) is already a decide-only capability
  (approve, defer, refuse), separate from `config`, `manage`, `unenrol` and `viewreports`.
- Nothing reads the course group mode. `rg 'groupmode|SEPARATEGROUPS|accessallgroups'` over the
  production code finds only comments. `can_manage_application()` (`lib.php:1316`) accepts the
  capability at system, course or applicant user context; the queue SQL (`scope_where()`,
  `classes/table/applications.php:392`) scopes by instance, site or mentees.
- A pending application has one group fact: `enrol_apply_submission.keygroupid`, the group whose
  key the applicant typed, 0 for an application without a key. The applicant joins groups only
  on approval: the decider's choice (`decidedgroups`) or, when none was recorded, the instance's
  list in `enrol_apply_groups` (`add_instance_groups()`, `lib.php:896`).
- **The key's group is dropped when a keyed application is approved without a group choice.**
  The fallback in `add_instance_groups()` reads only the instance's list, and the bulk decision
  form carries no group chooser. Harmless today (a keyed row is a key that failed to admit), wrong
  for the new mode.
- There is no decision history: a re-decision overwrites `decidedby` and `timedecided` on the same
  row, and the plugin fires no event for approve, defer or refuse (only
  `application_admitted_by_key`). Core's log store records only `user_enrolment_updated`,
  `role_assigned` and `group_member_added`.
- Core's edit-enrolment form approves too: a status change to active goes through
  `before_user_enrolment_updated` (`classes/hook_callbacks.php:53`) into `complete_approval()`
  with no plugin check beyond core's `enrol/apply:manage`.

## Stage 1: the fourth mode, "key only, reviewed"

`customchar1 = 'review'` (`groupkey::MODE_REVIEW`). The key is mandatory, as in `'required'`; a
valid key writes a pending application recording the key's group, as nobody is admitted.

| Situation | `'any'` | `'required'` | `'review'` (new) |
|---|---|---|---|
| Valid key, place and seat free | Admitted | Admitted | **Application waiting, `KEY_FOR_REVIEW`, group recorded** |
| No key, or a wrong key | Ordinary application / form error + `withoutkey` | Refused, nothing written | Refused, nothing written |
| Five wrong keys in 15 min | Field locks, application still possible | Nothing can be submitted | Nothing can be submitted |
| Valid key, course or group full | Application flagged `KEY_NO_PLACE` / `KEY_GROUP_FULL` | Refused (D1) | R2 |
| Approval | Decider's groups, else the instance's | n/a | Decider's groups, else **key group + instance's** (R4) |

### The predicates

`groupkey::required()` answers two different questions today. Split it:

- `groupkey::mandatory()`: `'required'` or `'review'`. Without a valid key nothing can be submitted.
  Card and button (`lib.php:259`), the refusals in `apply_or_admit()` (`lib.php:502`), the two-step
  form (`application_form.php:237`), the key block's required rule and no `withoutkey`
  (`:446`, `:515`), validation (`:774`), the edit form's P1/P1b warnings.
- `groupkey::admits()`: `'any'` or `'required'`. A valid key enrols at once. The admission path in
  `apply_or_admit()` and `lock_admission()`.
- `groupkey::required()` keeps its exact meaning, "nothing ever waits": the instance name
  (`lib.php:101`), "kept with your enrolment, nobody reviews" (`application_form.php:600`), the
  comment label (`commentlabel.php:80`), the empty-queue sentence (`renderer.php:121`),
  `announces_admissions()` and `customchar2` (`edit_form.php:103`).
- `groupkey::enabled()` gains `'review'`.

### Decisions

| # | Question | Recommendation |
|---|---|---|
| **R1** | Mode value and label | **`'review'`**. pt_br in the owner's words: "Só com chave: uma chave válida envia a inscrição para análise"; en "Key only, reviewed: a valid key sends the application for review, and nobody can apply without one". The `'required'` label becomes "Só com chave: uma chave válida inscreve na hora..." unchanged, so the two key-only options differ in their second half. |
| **R2** | Valid key, but the course has no place (`customint4`) or the group no seat | **Refuse, writing nothing, with the `'required'` sentences.** The key promises a seat in that group; queuing an application that cannot be honoured only moves the refusal to the moderator. The form's `capacity_refusal()` pre-check reuses as is. |
| **R3** | Key outcome recorded | **New `submission::KEY_FOR_REVIEW = 5`** in `KEY_OUTCOMES`. The existing flags all mean "a key that failed to admit". `create()`'s coherence rule (`submission.php:166`) holds unchanged: the outcome has a group. Label "Key accepted: {group}", queue badge, report filter option, privacy string. |
| **R4** | Approval without a group choice (bulk, empty chooser) | **Join the key's group as well** (`key_route_groups()`, key group + the instance's list) for `KEY_FOR_REVIEW` rows. The other outcomes keep today's fallback: their key failed, and `'any'` behaviour does not change. |
| **R5** | Seats at approval | **Warn, never block**, as today: the chooser already marks a full group (`decisiongroupfull`). Pending applications hold no seat, so thirty seats can draw fifty keyed applications; the decider sees the count. Blocking would be a new rule for every mode. |
| **R6** | The form | **Reuse the two steps of `'required'`.** Step 2's confirmation line says "Your application for {group} will be reviewed" instead of "You will join {group}", and the footer button reads "Submit application", not "Enrol me": `enrol_page.js` takes the label from a `data-` attribute on the form instead of a hardcoded string (`amd/build` rebuilt). |
| **R7** | Managers' notice | **The ordinary "new application" notice, naming the key's group** to a recipient who may see it (the admission notice's gate, `lib.php:~1975`). Stage 2 narrows the recipients. |
| **R8** | Default name of an unnamed instance | **"Application with a group key"** / "Inscrição com chave de grupo e análise", by the same `get_instance_name()` override as D6. |

Edit form: P1/P1b warn for `'review'` too (no keyed group, or every keyed group full: nobody can
apply). P2 ("switching does not decide them") does not apply as worded; it becomes "N
applications without a key are still waiting; they stay in the queue", which stage 2 makes
important (S3).

Settled during R1 (security read of the diff, 2026-11-05):

- **The record is the promise.** An approval joins the `keygroupid` recorded when the key was
  typed, even if the key has since been rotated or removed from the group, or the instance switched
  mode while the application waited. A group deleted meanwhile is skipped. Somebody decides every
  one of these applications, so sealing a group by removing its key stops new applications, not
  the ones already waiting.
- **Unticking every group still applies the defaults**, as it always has (`chosen_groups()` reads
  an empty recorded choice as none, `submission.php:765`); for `KEY_FOR_REVIEW` the defaults are
  the key route. A deliberate "no group" approval is not expressible today in any mode. S5/S6
  revisit the chooser.
- No admission lock for a review write: the capacity refusals are advisory reads, and approval
  past a full group is R5's warning, not a block.

Not changed: `'any'` and `'required'`, the throttle, `customchar2` (hidden outside `'required'`),
backup (customchar1 travels verbatim; an older plugin reads `'review'` as off).

## Stage 2: a queue scoped by separate groups

### The rule

A decider is **group-scoped** in a course when the course's group mode
(`groups_get_course_groupmode()`, `$course->groupmode`) is `SEPARATEGROUPS` **and** they lack
`moodle/site:accessallgroups` in the course context. This is core's own rule for the participants
page (`user/index.php:122-146`, `participants_search.php:423-447`), so a "moderator" role made from
the non-editing teacher archetype is scoped with no extra configuration, and an editing teacher
or manager is not.

An application belongs to a group by **`keygroupid`** while it waits; once decided, also by its
`decidedgroups`. A group-scoped decider sees and decides an application only when that group is
one of theirs (`groups_get_all_groups($courseid, $userid)`, core's visibility clause included).

Holders at system level (site-wide queue) and in the applicant's user context (mentors) are not
group-scoped: their delegation does not come from the course.

### Where the rule goes

One helper, `\enrol_apply\local\groupscope`, answering "is this viewer group-scoped in this
course, and with which groups", read by:

- `can_manage_application()` (`lib.php:1316`): the course branch also requires the application's
  group to be the viewer's. This is the per-row backstop of `confirm_enrolment()`,
  `wait_enrolment()`, `cancel_enrolment()`, the bulk operations and the review page.
- The queue: `scope_where()` and `scope_total()` gain the group predicate; `queue::scope()` and
  `neighbour()` (`queue.php:529-590`) too, so "next application" never leaves the scope.
- The group chooser on the review page and in the bulk decision form, and the allowlist in
  `confirm_enrolment()` (`lib.php:1406`, today every group of the course): the viewer's own groups.
- The notice recipients (`get_notifycoursebased_users()`, `lib.php:2027`): a group-scoped
  recipient is told only of applications in their groups.
- The report (`report.php`, `course_applications`): a base condition on the viewer's groups for a
  group-scoped holder of `viewreports`.
- The per-row icon on the participants page (`lib.php:1092`).

### Decisions

| # | Question | Recommendation |
|---|---|---|
| **S1** | What makes a decider group-scoped | **Core's rule: `SEPARATEGROUPS` without `moodle/site:accessallgroups`.** An own capability (`enrol/apply:accessallgroups`) would let a teacher be scoped here and not on the participants page, two answers to one question. |
| **S2** | Visible groups (`VISIBLEGROUPS`) | **Not scoped**: everybody sees and decides everything, the group filter (stage 3) defaults to the viewer's group, as the participants page does. Only separate groups separate. |
| **S3** | Applications without a group (no key, `'off'`/`'any'`, rows from before) | **Only for those who can access all groups**, as core treats a user in no group. The edit form of a separate-groups course warns when the mode lets such applications in ("applications without a key reach only those who can access all groups"); `'review'` is the mode that never makes one. |
| **S4** | A group-scoped decider in no group | **Core's "You are not a member of any group" notice** on the queue, no rows. |
| **S5** | Granular capabilities | **Two new course capabilities**, `manageapplications` keeps "decide": **`enrol/apply:choosedecision`**: change the groups and role an approval gives (the choosers). Without it the approval gives the key route and the instance's role, and the review page shows them read-only. This is the "only approve or refuse" the owner asked for. **`enrol/apply:viewapplications`**: read the queue and the review page without deciding (a coordinator). Archetypes: both to editingteacher and manager, so nothing changes on existing sites. |
| **S6** | Can a group-scoped decider with `choosedecision` move the applicant to another group | **Only among their own groups.** |
| **S7** | Core's edit-enrolment backdoor (`enrol/apply:manage` + status change) | **Documented, not closed**: a moderator role must not hold `enrol/apply:manage`. The README's moderator recipe says so, and the hook gains the same group check if a hook can refuse (S0 spike); if it cannot, the recipe is the defence. |
| **S8** | The report for a group-scoped `viewreports` holder | **Scoped like the queue** (key group or decided groups). `viewreports` stays manager-only by default. |
| **S9** | Upgrade | **No data change.** The rule is computed per request; capabilities are added by `db/access.php` with a version bump. |

### S0. Spikes before code

1. Can `before_user_enrolment_updated` refuse the update (S7)?
2. A report builder base condition built from the viewer's groups: one `IN` over `keygroupid`
   plus an `EXISTS` over `decidedgroups` (a comma list: `FIND_IN_SET` is not portable; measure a
   `LIKE` on `','||decidedgroups||','` on both families, or record decided groups in a child
   table if that is too slow).
3. The cost of `groupscope` per queue page: one `groups_get_all_groups()` per course, cached per
   request.

## Stage 3: the screens, with groups in them

Starts with a **review of the screens with mockups** (queue, review page, bulk decision, report,
datasource, the applicant's pages), as [`applications-desk.html`](applications-desk.html) did, for
the owner to approve before code. What it should at least carry:

- **Queue**: a group column (name gated by `groupvisibility::visible_to()`, as the report column
  already is) and a group filter in `applications_filterset` (the viewer's groups, plus "without a
  group" for those who see them), intersected server-side with the scope.
- **Review page**: the key's group stated plainly, with seats; read-only route when the viewer
  lacks `choosedecision`.
- **Report and datasource**: a group filter over `keygroupid` and decided groups (core's
  `core_group` entity has no group-id filter), a "decided groups" column.
- **Audit trail**: decisions leave no event and a re-decision erases the previous one.

| # | Question | Recommendation |
|---|---|---|
| **T1** | Events for decisions | **Yes: `application_approved`, `application_deferred`, `application_refused`**, `relateduserid` the applicant, `other` carrying the groups and role. The log store becomes the audit log, and core's log report can filter it. |
| **T2** | A decision history table | **Not now**: the events keep the history; a table only if the owner wants it in the plugin's own report. |
| **T3** | Which applicant pages change | None for group scope; they get the R6 wording only. |
| **T4** | Mockups first | **Yes**, one HTML page under `docs/design/`, before any stage 3 code. |

### T0: the screen review (2026-11-05, decided)

The owner accepted every recommendation of `group-screens.html` (V-D1-V-D14) and of
[`review-page-concepts.html`](review-page-concepts.html) (RP-D1-RP-D28: the review page becomes
concept A, the decision funnel, with search past eight choosable groups) on 2026-11-05. Slices in
order: V1 events, V2 queue, V5 joined groups and the report, V3 the review page as concept A, V4 the
bulk form; V6 stays optional.

[`group-screens.html`](group-screens.html) is T4's page: mockups of the queue (desktop, phone, the
viewer and the moderator in no group), the review page (with and without `choosedecision`), the
participants-page bulk form, the course report and core's log report, read against f9f3981 and core
5.3. What it found that this plan did not say:

- **The queue's two tiles disagree for a narrowed decider.** "Awaiting decision" is `scope_total()`,
  narrowed; "Deferred to the waiting list" is `capacity::deferred()`, method-wide
  (`renderer.php:167`, `:509-513`).
- **A viewer never sees the key group's name on the review page**: `key_context()` gates it on
  `manageapplications` (`renderer.php:815`).
- **`decidedgroups` is the choice, not what was joined.** Empty means the method's defaults, which is
  what every decider without `choosedecision`, every default approval and core's Edit enrolment record,
  so S8's narrowing (key group or decided groups) misses an approval the defaults put into a group.
- **The participants-page bulk route is nearly unreachable for a moderator**: core shows them only
  members of their groups (`user/index.php:122-146`), and an applicant joins a group on approval.
- **T1's approved event cannot simply be fired in `complete_approval()`**: it runs twice for a queue
  approval and again for a key admission. A request-scoped marker in `classes/local/` makes it once.
- **Core's log report narrows by the acting user's groups** (`report_helper::get_group_filter()`), has
  no event-name filter, and the log store deletes only rows the subject performed. The course report
  stays the moderator's surface; the events' `other` carries ids only.

Slices V1 (events, tier 3), V2 (queue column, filter, scoped tiles, tier 3), V3 (review page, tier
2), V4 (bulk form, tier 2), V5 (`joinedgroups`, report filter and column, tier 3), V6 (optional
history panel from the log reader). Decisions V-D1 to V-D14 are in the page; V-D5 proposes
`application_cancelled` for T1's `application_refused`, and V-D9 keeps T2 (no table).

## Stages

Each stage leaves the plugin releasable, with a version bump and `CHANGELOG.md`. Supported range
stays `[501, 503]`.

| Stage | Content | Tier |
|---|---|---|
| R1 | Server: `MODE_REVIEW`, the three predicates, `apply_or_admit()`, `KEY_FOR_REVIEW`, R4 fallback, R7 notice, R8 name, settings and edit form option, tests and gates | 3 |
| R2 | Surfaces: card, two-step wording, `enrol_page.js` label, badges, report option, edit form warnings, strings, Behat, README | 2 (`--behat`) |
| S0 | Spikes, nothing committed | - |
| S1 | `groupscope`, `can_manage_application()`, queue, neighbours, choosers, allowlist, notices, participants page | 3 |
| S2 | `choosedecision`, `viewapplications`, the report scope, README moderator recipe | 3 |
| T0 | Screen review with mockups, owner decision | - |
| T1+ | As T0 decides; T1 (events) is tier 3 | 2-3 |

R1 and S1 get `security-diff-read` over their diffs: R1 is a route into the queue, S1 is the
capability state machine.
