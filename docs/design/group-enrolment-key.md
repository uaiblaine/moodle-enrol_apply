# Group enrolment keys: admission without a decision

Status: **decided 2026-10-23: the owner accepted every recommendation, G1&ndash;G15.** K0 (spikes)
done the same day: no design change, three corrections (no trimming, a lowest-id tie-break, the
key ignored whenever `withoutkey` is set). The prerequisites, K1 and K2 are written
(2026-10-23, uncommitted): `\local_groupdist\seats` on groupdist's main, 5.1 and 5.3 branches,
the `extra_deps_for()` case in moodle-dev, and here the setting, the resolver, the throttle and
the seats reader. K1 to K6 are merged (#103 to #107); K7 and K8 close the plan. Mockups: [`group-enrolment-key-mockup.html`](group-enrolment-key-mockup.html).
Revised the same day for per-group seats from `local_groupdist` (G12&ndash;G15).

## The request

The owner's words, kept verbatim:

> Se o usuário tem uma chave de grupo válida, não precisa aguardar aprovação e depois de
> preencher os campos obrigatórios ele é inscrito no curso e vinculado ao grupo. Se a chave
> não for válida, sugerir seguir com o processo e aguardar a aprovação.

So: an applicant who holds a valid group key still fills in the application (every required
profile field and question), and on submit is **enrolled at once, active, in that group**,
with no manager in the loop. A wrong key does not dead-end the applicant: they are offered
the ordinary route, an application that waits for a decision.

Second request, same day:

> Sobre vagas, se o plugin moodle-local_groupdist estiver disponível, ele adiciona um campo
> extra de vagas por grupo, neste cenário o plugin enrol_apply pode considerar essas vagas por
> grupo.

So: where `local_groupdist` is installed, a group's own seats join the course's `places` as a
second capacity, on the key route as a gate and on the approval route as information.

## What core does, and what we keep from it

Measured on `moodle-503/public/enrol/self`:

| Fact | Where | Consequence here |
|---|---|---|
| The key lives on the group: `{groups}.enrolmentkey`, edited on the group's own settings page. | `group/group_form.php`, `lang/en/group.php` `enrolmentkey_help` | Reuse it. Teachers already know where a group key is set, and one key per group is what `enrol_self` reads too. |
| Core refuses a key already used by another group of the same course. | `enrolmentkeyalreadyinuse` | A key resolves to **at most one** group, so "which group" is never ambiguous. |
| `enrol_self` honours group keys only when the instance ALSO has a course key and `customint1` ("Use group enrolment keys") is on. | `enrol/self/lib.php:194`, `classes/form/enrol_form.php:121` | Here the key is **optional**: there is no course key, and no key means an ordinary application. The opt-in flag is kept (G2). |
| Match is `$group->enrolmentkey === $data->enrolpassword`, groups with an empty key skipped. | `enrol/self/locallib.php:38` | Keep the empty-key skip (it is a guard and gets a mutation). Use `hash_equals()` instead of `===`. |
| `groups_add_member($group->id, $USER->id)` with **no component**. | `enrol/self/lib.php:202` | Do NOT copy: this plugin stamps every membership `enrol_apply` + instance id so `unenrol_user()` removes it and the privacy provider (already) covers it. |
| No throttle on wrong keys; the hint setting can leak the first letter. | `enrol_form.php:130` | Here a wrong key costs the applicant nothing (they can still apply), so the form is a free oracle. Add a throttle (G7); never a hint. |

## Per-group seats: what `local_groupdist` provides

Read on `moodle-local_groupdist` `main` (4c0149d) and its branches:

| Fact | Where | Consequence here |
|---|---|---|
| Seats are **not** a groupdist table: a core group custom field (`core_group` / `group` handler, `number` type), shortname `groupdist_seats`, value in `{customfield_data}.decvalue`. | `classes/local/fields.php` `SHORTNAME_SEATS`, `get_seats_field()` | The value outlives nothing special: it is backed up and restored with the group by core, and a teacher edits it on the group form or groupdist's bulk edit page. |
| An **empty** value means "no limit declared"; `0` is a real capacity of zero. | `fields::get_group_values()` (`seats` null), `bulkedit_page.php:173` | Same reading here: null never blocks, 0 is full. |
| Occupancy is **every** `{groups_members}` row of the group, whatever the member's role or component. Over = `members > seats`. | `fields::get_member_counts()`, `bulkedit_page.php:184` | Use the same count, so the number enrol_apply shows and the red indicator on groupdist's bulk edit page never disagree (G13). |
| The field is provisioned on install under a lock, and deleted on uninstall. A deleted field returns null. | `fields::ensure_fields_exist()`, `delete_provisioned_fields()` | "Available" = the groupdist class exists AND the seats field exists. Either missing reads as "no group declares seats", the behaviour without groupdist. |
| groupdist ships one branch per Moodle version: `MOODLE_405_STABLE`, `_501_`, `_502_` (= `main`), `_503_`. | `version.php` `supported` per branch | Every version enrol_apply supports (5.1&ndash;5.3) has a groupdist build that can carry the reader (G12). |
| groupdist's own *Apply distribution* fingerprints each group's current count and refuses when it changed since the preview. | `CLAUDE.md` "Determinism is the contract" | A key admission landing between a groupdist preview and its apply is caught by groupdist itself: its run refuses and asks for a new preview. Nothing to coordinate from here. |
| `local_*` plugins have no enable switch. | core | "Installed" is the whole availability test; there is no "disabled groupdist" state to handle. |

### Where seats are read

| Route | Seats are | Why |
|---|---|---|
| **Key route** (no human) | a **gate**: a target group out of seats sends the applicant to the queue, flagged | With nobody in the loop, the declared capacity is the only judge, exactly like `places` under G4. |
| **Approval route** (a decider) | **information**: the group chooser shows `members/seats` and marks full groups; approving into a full group warns after the redirect | `places_full()` is advisory by decision and the decider may knowingly overbook a group too; a hard block would have to be re-implemented on the queue, the review page and the participants page. |
| **Applicant without a key** | nothing | They are not choosing a group; telling them a group is full discloses a group they may not know of. |
| **Instance settings** | shown beside each group that carries a key | The teacher sees which keys would currently admit nobody. |

## Where it lands in this plugin

| Concern | Today | With group keys |
|---|---|---|
| Card on `enrol/index.php` | `enrol_page_hook()`, `lib.php:198` | One extra sentence when the instance accepts keys. |
| The form | `classes/form/application_form.php`, modal + `apply.php` | A key field at the top; an "apply without the key" escape after a wrong key. |
| Write door | `submit_application()`, `lib.php:358`, under a per-user lock | Re-resolves the key **inside the lock** and chooses between `apply()` and admission. |
| Becoming active | `confirm_enrolment()` stamps dates, `update_user_enrol()`, `complete_approval()` | Extract the activation half into one protected method both routes call. `confirm_enrolment()` keeps its `can_manage_application()` gate; the key route has its own (the key). |
| Groups | `add_instance_groups()` reads `submission::chosen_groups()` or the instance list | The key route records `decidedgroups` = instance list &cup; key group (G10), so the existing reader does the rest, idempotently, on both passes. |
| Record | `enrol_apply_submission` (`status`, `decidedby`, `decidedgroups`) | New `keygroupid`: the group whose key was presented, 0 for none. The recorded fact behind every later display. |
| Acknowledgement | `applied.php` + `applicantstate::describe()` | An ACTIVE row with access already reads "you are enrolled"; add the group and a "Go to course" button. |
| Queue, review, report | `classes/local/queue.php`, `manage.php?userenrol=`, Report Builder entity | A chip on the review page and the queue row, a report column "Admission route". |

## The flow

```
applicant submits the form (validation already passed: required fields, questions)
        │
        ▼  submit_application(): lock (instance, user)
  allow_apply()  ──refused──▶ refusal, as today (window, cohort, disabled, new enrolments off)
        │
  already a row? ─────────────▶ already_applied, as today
        │
  applications_closed() ─────▶ refused, as today (applicant limit, G5)
        │
  key empty / instance off ──▶ apply()  → PENDING (today's route)
        │
  groupkey::resolve() == null ▶ apply()  → PENDING (only reachable with "without the key" ticked)
        │ group G
  capacity::places_full() ───▶ apply() + keygroupid = G → PENDING, flagged "valid key, no place" (G4)
        │
        ▼  lock per target group (enrol_apply_groupseat, key = group id, ids ascending)
  groupseats::full(targets) ─▶ apply() + keygroupid = G → PENDING, flagged "valid key, group full" (G14)
        │                      (targets = {G} ∪ instance groups; only with local_groupdist)
        ▼
  admit(): enrol suspended → record (keygroupid G, decidedgroups) → activate()
           → ACTIVE, role, groups ∪ {G}, decided by the applicant, event, notifications (G8)
  release the group locks
```

**The seat check and the membership write share one lock per group**, held across both. The
lock in `submit_application()` is per instance AND user, so two different key holders racing
for the last seat of Turma B never meet there: both would count 19 of 20, both would join.
The group locks are taken in ascending id order so two admissions targeting overlapping
groups cannot deadlock, and with a short timeout: a lock not obtained sends the applicant to
the queue (flagged, never refused), the same treatment a full group gets. The approval route
does not take them: it does not gate on seats.

The key is resolved **twice**: in `validation()` for the message, and again inside the lock for
the write, from the database and never from anything the form carried. A key rotated, a group
deleted or the option switched off between the two reads sends the applicant down the PENDING
route instead of admitting them on a stale fact. (The fleet rule: decide on the recorded fact.)

## Decisions for the owner

Each has a recommendation; the mockup shows the recommended one.

| # | Question | Options | Recommended, and why |
|---|---|---|---|
| **G1** | Where do the keys live? | a) core `{groups}.enrolmentkey`; b) a key table of our own, per instance | **a.** One place, the one core and `enrol_self` already use; uniqueness per course comes free. b) duplicates a core feature and lets two methods disagree about a group's key. |
| **G2** | Is it opt-in per instance? | a) per-instance setting, off by default; b) always on | **a.** Keys left on groups from an old `enrol_self` setup are inert today. Switching this on silently would turn each of them into an approval bypass. Stored in `customchar1` (never used; `customint1`/`customint2` carry legacy meanings that `db/upgradelib.php` still reads). |
| **G3** | Which groups' keys count? | a) any group of the course with a key (core parity); b) only groups the teacher ticks on the instance | **a for v1.** b) is a real need only for a course that mixes approval and self-enrolment with keys for different audiences; it is additive later (a second table, like `enrol_apply_groups`). The settings page lists which groups have a key, so nothing is hidden. |
| **G4** | Valid key, but `places` (customint4) is full? | a) fall back to PENDING with the key recorded; b) refuse; c) admit anyway | **a.** `places_full()` is advisory by decision so that a person decides; with no person in the loop the advisory becomes the only judge. The manager sees "valid key for Group A, no place" and approving pre-selects Group A. |
| **G5** | At the applicant limit (customint3)? | a) refused like everybody; b) a key bypasses it | **a.** The limit is "how many people may enter the pipeline"; a key holder is one of them. One predicate for all, as today. |
| **G6** | What does a wrong key do? | a) form error plus an "apply without the key" checkbox; b) error only; c) silently become a PENDING application | **a.** Matches "sugerir seguir com o processo": typos get a second try, and the way out is one tick, explained. c) hides a typo the applicant would have fixed. |
| **G7** | Throttle wrong keys? | a) 5 wrong keys per user and instance in 15 minutes, then the field locks (application still possible); b) none, as core | **a.** Unlike `enrol_self`, a wrong key here costs nothing, so the form is an unlimited free oracle for keys that skip a human decision. MUC application cache, key `<instanceid>_<userid>` (no `:`), TTL 900 s. |
| **G8** | Who is told? | Managers: a) informational "X joined with the key of Group A"; b) nothing. Applicant: c) no mail, the page says it; d) an "you are enrolled" mail | **a + c.** Managers keep sight of who came in without them, with a distinct subject so it is not mistaken for a pending application. The applicant is on the page that tells them; the "approved" mail would claim a decision nobody took. |
| **G9** | Name the group on success? | a) yes; b) no | **a.** Only a key holder sees it, and they become a member of it the same second. Never named on the wrong-key path. |
| **G10** | Groups joined | a) key group &cup; the instance's configured groups; b) key group only | **a.** The instance list means "every admitted applicant joins these"; a key adds one, it does not replace the rule. |
| **G11** | Who is `decidedby`? | a) the applicant, with `keygroupid` saying why; b) 0 | **a.** `decidedby = 0` already means "zeroed on course deletion"; reusing it would make two facts indistinguishable. The report reads the route from `keygroupid`, not from `decidedby`. |
| **G12** | How does enrol_apply read groupdist's seats? | a) a small public reader added to groupdist (`\local_groupdist\seats::for_groups(array $groupids): array`, outside `local\`), on all four branches, reached through `class_exists()`; b) call `\local_groupdist\local\fields::get_group_values()` and `get_member_counts()` directly; c) read `{customfield_data}` by the shortname `groupdist_seats` with no groupdist code | **a.** `local\` is groupdist's internal namespace, free to change; a named public method is the contract, the way `enrol_apply_plugin::is_full()` is the contract three other plugins rely on. c) works without groupdist installed, but it reads a convention and would keep enforcing seats from a field left behind by a half-removed plugin. An older groupdist without the class reads as "no seats", never as an error. |
| **G13** | What occupies a seat? | a) every member of the group, any role (groupdist's own count); b) only active enrolments of this method | **a.** The teacher manages seats on groupdist's bulk edit page, whose red indicator counts every member. Two definitions would show "19/20" here and "21/20" there for the same group. A teacher placed in the group does take a seat, in both plugins. |
| **G14** | Which groups' seats gate a key admission? | a) every group the admission would add the applicant to (key group &cup; instance groups); b) the key group only | **a.** The record says which groups the admission joins; checking only one of them lets the instance's other groups overflow with nobody deciding. An instance group without declared seats never blocks, so the usual "everyone joins All participants" setup is unaffected. |
| **G15** | Approving into a full group (approval route) | a) allowed, with seats shown in the chooser and a warning after the decision; b) blocked | **a.** Same reasoning that keeps `places_full()` advisory: a person decides, and the plugin tells them what they are deciding. A block would also need a channel on the participants page's per-row icon, which has none. |

Out of scope, recorded so nobody assumes otherwise: a course-level key (not a group's) that
skips approval; keys for the waiting list; the Moodle App (the plugin has no web service, and
the form reaches the app only as a web view).

## Stages

Each stage leaves the plugin releasable. Version bump and `CHANGELOG.md` in the stages that
touch `db/` or `amd/`. Supported range stays `[501, 503]`.

### K0. Spikes &mdash; DONE 2026-10-23, nothing committed

Throwaway scripts run with `docker exec -u www-data <stack>-webserver-1 php`, read-only except
K0.5, whose scratch course, users and recycle-bin copy were deleted afterwards (m501: 0 left).

1. **An element added in `definition_after_data()` survives the modal round trip: yes, on 5.1,
   5.2 and 5.3.** Measured through the real web service path,
   `\core_form\external\dynamic_form::execute()`, with a throwaway `dynamic_form` (key field,
   one required field, the checkbox added only when the posted key is wrong). Core's order is
   what makes it work: `is_validated()` calls `definition_after_data()` and THEN validates, and
   the web service renders that same object when validation fails. Seven cases, identical on
   the three branches:

   | Posted | Submitted | Checkbox rendered | Key error |
   |---|---|---|---|
   | wrong key, required field filled | no | **yes** | yes |
   | wrong key + `withoutkey` ticked | **yes**, data still carries `groupkey = 'wrong'` | &mdash; | no |
   | ticked, key absent (what a browser posts once `disabledIf` disabled the field) | yes, `groupkey` null | &mdash; | no |
   | right key, required field empty | no | no | no (field error only) |
   | right key, required field filled | yes | &mdash; | no |
   | no key, required field filled | yes | &mdash; | no |

   Consequences: the wanted design holds, no fallback needed. **The server must ignore the key
   whenever `withoutkey` is set**, because the page transport (no JavaScript, so no
   `disabledIf`) posts the wrong key together with the tick (row 2). One gate for it in K7.
2. **`passwordunmask` in the modal: its JavaScript arrives in the payload the modal runs**
   (`javascript`, not `html`: one `core_form/passwordunmask` initialiser per render, 5.1&ndash;5.3).
   The click itself is a browser fact, held by the K5 Behat scenario.
   **Unexpected and decisive: the element refuses a value with wrapping whitespace on its own**
   (`MoodleQuickForm_passwordunmask::validateSubmitValue()`, `err_wrappingwhitespace`, "The value
   must not start or end with whitespace.", identical on 5.1&ndash;5.3). Core's group form uses the
   same element, so a key typed on the group page never carries wrapping whitespace either.
   Consequences: **the resolver does NOT trim** (it compares exactly, as `enrol_self` does), and
   `validation()` does not resolve, or count against the throttle, a value the element already
   refused.
3. **The per-course uniqueness of group keys is a FORM rule only**, identical on 5.1&ndash;5.3
   (`group/group_form.php`, `enrolmentkeyalreadyinuse`). Nothing in the schema enforces it
   (`groups.enrolmentkey` is a nullable `char(50)` with no index), and two write paths skip it:
   the CSV import (`group/import.php` maps an `enrolmentkey` column) and the web service
   `core_group_create_groups` (`group/externallib.php`, `PARAM_RAW`, no check). So duplicates and
   keys with wrapping whitespace are reachable. Consequences: **tie-break on the lowest group id**
   (what `enrol_self` does, iterating `id ASC` and stopping at the first match); the settings
   page (K1) flags a key shared by several groups ("only Turma A's admits") and a key that
   cannot be typed into the form (wrapping whitespace).
4. **CI can install groupdist per job, on both sides.**
   - GitHub: the reusable workflow's `plugin-dependencies` input takes `repository,branch`
     per line; `uaiblaine/moodle-local_groupdist` is public, so no token. Each job names its own
     branch: `MOODLE_501_STABLE`, `MOODLE_502_STABLE`, `MOODLE_503_STABLE`.
   - Local `mdl ci`: dependencies come from `$plugin->dependencies` plus `extra_deps_for()` in
     `moodle-dev/bin/mdl-ci`, which already has this exact shape for `customfield_pattern` (an
     optional provider reached through `class_exists()`) and per-branch checkouts. groupdist
     has them: `moodle-local_groupdist-501`, `moodle-local_groupdist` (main = 5.2),
     `moodle-local_groupdist-503`, and `plugins.conf` already mounts each on the stacks
     enrol_apply runs on (m501; m502, m502b; m503, m503b). Needs one `moodle-dev` pull request
     (a `moodle-enrol_apply` case), or `mdl ci --matrix` warns that ci.yml installs a sibling the
     local run does not.
   - Order that follows: groupdist's `seats` class lands on its three branches BEFORE the
     enrol_apply commit that adds `plugin-dependencies`, or every leg installs a groupdist
     without it and tests only the "absent" path while reading green. The seam (K2) keeps both
     paths covered on every leg regardless.
5. **The last-seat race is real and the group lock closes it.** Two PHP processes on m501, a
   group with one seat, both released at the same instant, a one-second gap between the count
   and the write:

   | | Process 1 | Process 2 | Members after |
   |---|---|---|---|
   | no lock | saw 0/1, admitted | saw 0/1, admitted | **2** |
   | `enrol_apply_groupseat` lock on the group id | saw 0/1, admitted | saw 1/1, sent to the queue | **1** |

   (The gap widens a window that is milliseconds in production; it makes the race
   deterministic, it does not create it. The per-user lock of `submit_application()` cannot
   help: the two applicants are different users.)

### K1. Instance setting &mdash; written 2026-10-23

- `customchar1`: `''` off, `'any'` accept any course group's key (leaves room for `'listed'`, G3).
- `edit_form.php`: a "Group enrolment keys" header, the select, a help button, and a read-only
  line: how many groups of the course carry a key, with a link to `group/index.php`. The line
  warns when the option is on and **no** group has a key, when several groups share one key
  (only the lowest id admits, K0.3), and when a key has wrapping whitespace (the form's
  element refuses it, so nobody can type it, K0.2).
- `get_instance_defaults()` and a site default in `settings.php` (`enrol_apply/groupkeys`).
- Lang: `groupkeys`, `groupkeys_help`, `groupkeys_any`, `groupkeys_off`, `groupkeysingroups`,
  `groupkeysnone`, `config_groupkeys`, `config_groupkeys_desc`; en and pt_br in lockstep.
- Backup carries `customchar1` with the instance; restore needs nothing (keys travel with the
  groups).

### K2. Resolver and throttle &mdash; written 2026-10-23

As built: `groupkey::summary()` (not `courses_groups_with_keys()`) feeds the settings list,
with shadowed and untypable keys; `groupseats` reads `members` as null when local_groupdist is
absent. Tests that need seats provision local_groupdist's field first: its `db/install.php`
skips provisioning during an initial install, which is how every PHPUnit site is built, and on
such a site the field appears only when its pages are first used (until then: no seats).

- `classes/local/groupkey.php`:
  - `enabled(stdClass $instance): bool`
  - `resolve(stdClass $instance, string $key): ?int` &mdash; returns null for an empty key or a
    disabled instance, scans the course's groups with a non-empty key in `id ASC` order and
    returns the first `hash_equals()` match. No trimming (K0.2): the comparison is exact.
  - `courses_groups_with_keys(int $courseid): int` for the settings line.
- `classes/local/keyattempts.php` over a new MUC definition `keyattempts` (application, simple
  keys, TTL 900): `record_failure()`, `locked()`, `clear()`. `db/caches.php`,
  `cachedef_keyattempts`.
- `classes/local/groupseats.php`, the only reader of groupdist in this plugin:
  - `available(): bool` &mdash; `class_exists('\local_groupdist\seats')`.
  - `for_groups(array $groupids): array` &mdash; groupid &rArr; `{seats: ?int, members: int}`;
    every seats null when groupdist is absent.
  - `full(array $groupids): array` &mdash; the ids at or over their seats.
  - A protected seam the tests override, so the suite covers "groupdist present" and "absent"
    on every leg whatever K0.4 finds.
- **In `local_groupdist`** (its own pull request, on `main` and backported to the
  `MOODLE_405/501/503_STABLE` branches, with a version bump each): `classes/seats.php`, a public
  `for_groups()` built on `fields::get_group_values()` and `get_member_counts()`, its PHPUnit,
  and a README line naming the consumer. Nothing else in groupdist changes.

### K3. Record &mdash; written 2026-10-24

As built: the outcome is a column of its own beside the group (`keyoutcome`, KEY_NONE,
KEY_ADMITTED, KEY_NO_PLACE, KEY_GROUP_FULL), both written by `submission::create()` inside its
existing transaction (the write door knows the outcome before it writes), not by a separate
writer. `create()` refuses a group without an outcome and an outcome without a group. The
restore keeps the outcome when the group did not travel, and drops an outcome outside the
vocabulary. Nine gates, KR1&ndash;KR9.

- `enrol_apply_submission.keygroupid` int(10) default 0, upgrade step with its savepoint,
  `install.xml` `VERSION` unchanged rule respected.
- `submission::record_key_group()`, `submission::key_group()`.
- Backup: annotate `keygroupid` as a group id and map it on restore (0 when unmapped).
- Privacy: metadata string for the field; export it as the group's name.

### K4. Write door &mdash; written 2026-10-24

As built: `apply_or_admit()` behind `submit_application()`'s checks; `key_route_groups()` and
`lock_admission()` (the method's lock, then one per group ascending, 5 s each; the places
check moved under it after the security read found two key holders could take the last
place), and `admit()`, one transaction, so a failed activation leaves no "admitted" record
behind. A lock not obtained records a fifth outcome, `KEY_SEAT_UNCONFIRMED`, rather than
claiming nothing was left. The result object carries
the outcome (`created($outcome, $groupid)`, `was_admitted()`), not separate constructors.
Fourteen gates, KW1&ndash;KW14.

- Extract `activate(stdClass $instance, stdClass $userenrolment): void` from
  `confirm_enrolment()` (dates, `update_user_enrol()`, `complete_approval()`). The gate stays in
  the callers.
- `submit_application()`: after the existing checks, inside the lock, `groupkey::resolve()`
  from `$data->groupkey` unless `$data->withoutkey`; then the branches of the flow above.
- `apply()` gains a mode so the admission route does not send the "awaiting your decision"
  notification.
- The group seat gate (G14): target groups = key group &cup; instance groups, locks
  `enrol_apply_groupseat` per group in ascending id order (timeout 5 s), `groupseats::full()`
  inside them, then admit; released in `finally`. Skipped entirely when
  `groupseats::available()` is false.
- `application_result::admitted(int $groupid)`, `::pending_no_place(int $groupid)` and
  `::pending_group_full(int $groupid, array $fullgroupids)`.
- `enrol_apply_submission.keyoutcome` (smallint: 0 none, 1 admitted, 2 no place, 3 group full),
  so the review page and the report say WHICH capacity stopped the admission from the recorded
  fact, not from recomputing seats that have moved since. (K3 gains this column.)
- Event `\enrol_apply\event\application_admitted_by_key` (objecttable `enrol_apply_submission`,
  `other.groupid`), lang `eventapplicationadmittedbykey`.
- `notify_approval` task: skip the "approved" message when `keygroupid` is set and the status
  was decided by the applicant (G8).

### K5. Form &mdash; written 2026-10-25

As built: the field has no header of its own (a header stays open over every later element, so
an instance asking for nothing would have put its questions inside it); an admission goes to
`course/view.php` with a success message naming the group, not to `applied.php`, whose
"approved" wording would claim a review until K6 reworks it. Nine gates, KF1&ndash;KF9, and a
Behat feature (`group_enrolment_key.feature`, page transport) for the two journeys.

- `application_form::definition()`: when `groupkey::enabled()`, a `passwordunmask` element
  `groupkey` directly after the introduction (it decides the route, so it is read first), with
  a help button. When `keyattempts::locked()`, a static line instead of the field.
- `validation()`: non-empty key that does not resolve and `withoutkey` not ticked &rarr;
  error on `groupkey` naming the way out; `keyattempts::record_failure()`. Never says why
  (no "no such group", no hint).
- `definition_after_data()` (or the K0 fallback): the `withoutkey` advcheckbox.
- `process_dynamic_submission()`: admitted &rarr; `applied.php` (the success state);
  `\core\notification::success()` naming the group.
- `enrol_page_hook()`: the extra sentence on the card when the instance accepts keys.

### K6. Surfaces &mdash; written 2026-10-25

As built: `applicantstate` refines only APPROVED and PENDING, and only while the record still
says what the key did &mdash; an admission whose decider is still the applicant
(`submission::admitted_by_key()`), a pending application whose key ran into no place, a full group
or an unconfirmed seat. A deferral reads as deferred, a key group deleted since leaves the plain
wording, and the form's refusal gets the group through `message_param()`. The queue's badge
carries the outcome without the group's name (the mentee scope spans courses whose groups a mentor
may not see); the review page names it to a reader holding the course capability and ticks
`key_route_groups()`, now public, because the chooser's pick replaces the method's list. The
chooser shows seats only for a group that declares them; `groupseats::overfilled()` reads the
memberships an approval stamped and feeds `manage.php`'s warning (members &gt; seats, a group
exactly at its seats is full, not over). The manager notice reuses the `application` provider and
recipients, with type `admission` in `enrol_apply_notification`; it is sent from inside `admit()`'s
transaction, which core's message buffer makes safe, and names the key group only to a recipient
holding the course capability (the security read found it reaching mentors and site-wide
recipients). The instance settings line already showed
seats from K1. Nineteen gates, KS1&ndash;KS19.

- `applicantstate`: ACTIVE with access and `keygroupid` &rarr; "You are enrolled and joined
  {group}"; PENDING with `keygroupid` &rarr; "Your key is valid, but there are no places left.
  Your application is waiting for a decision."
- `applied.php`: "Go to course" button on the admitted state.
- `applicantstate`: PENDING with `keyoutcome` = group full &rarr; "Your key is valid, but
  {group} has no seats left. Your application is waiting for a decision." Names only the key
  group the applicant typed the key for, never an instance group (that would disclose a group
  they were not told about).
- Review page and queue: a chip ("Joined with a group key: Group A" / "Valid key, no place" /
  "Valid key, group full: Group A"); the approval group chooser pre-ticks the key group.
- **Group chooser with seats** (review page and queue, only when `groupseats::available()`):
  each group reads `Turma B · 20/20`, a full group carries a "Full" badge and stays selectable
  (G15). After an approval that took a group over its seats, `manage.php` warns before the
  redirect, beside the existing places warning and for the same reason (never from
  `complete_approval()`, which runs twice).
- Instance settings: the keyed-groups line shows each group's seats when groupdist is there
  (`Turma B 20/20 · full`), and says when a keyed group currently admits nobody.
- Report Builder: column "Admission route" (decision / group key) and "Key group".
- Manager notification with its own subject and body strings (G8).

### K7. Tests and gates &mdash; written 2026-10-25

As built: most of the list landed with the stage that wrote each guard (K1&ndash;K6). An audit
against it found nine gaps, closed here: the key read again at the write when it was rotated,
the option switched off or the group deleted after the form checked it; a key holder at the
applicant limit and outside the cohort; every member taking a seat whatever their role and
component; a deleted Seats field reading as no seats; a Behat scenario with the option off; and
gate GK9 for the case-sensitive comparison. Two items stay without a gate on purpose: the
re-read at the write door (the form passes only the typed key, so there is no stale source to
mutate towards) and the checks being inside `lock_admission()` (only two concurrent requests
can show it, K0.5). The groupdist-absent branch of the admission is `groupseats::full()`'s own,
held by `groupseats_test` through its `available()` seam.

- PHPUnit: resolver (empty stored key never matches, empty submitted key, another course's
  group, option off, no trimming, case-sensitive, two groups sharing a key resolve to the
  lowest id); throttle (a value refused for wrapping whitespace does not count); `withoutkey`
  ignores a posted key (K0.1, the page transport); write door (admitted, pending without
  key, pending no place, key rotated between validation and write, option switched off between
  them, group deleted between them, applicant limit, cohort); group union and component stamp;
  `decidedby`; event; notification suppression; privacy export of `keygroupid`; backup and
  restore mapping.
- PHPUnit, seats (each with groupdist present and absent through the seam): empty seats never
  blocks, `0` blocks, members counted across roles and components, an instance group full
  blocks the admission (G14), the seats field deleted reads as no seats, the group lock not
  obtained sends to the queue, `keyoutcome` recorded for each branch, the chooser's counts and
  the post-approval warning. In groupdist: `seats::for_groups()` against `fields`' own readers.
- `mutations/gates.conf`: one gate per guard (empty-key skip, `hash_equals`, in-lock
  re-resolve, places fallback, throttle lock, `withoutkey`), swept on pgsql and mariadb
  **before** the commit that adds them.
- Behat (`@enrol_apply`): valid key &rarr; enrolled and in the group; wrong key &rarr; error,
  tick &rarr; pending; option off &rarr; no field.
- Before the push: `security-diff-read` over the diff (this is an approval bypass: state
  machine and authentication). An MDL Shield review only if the owner says so for this pull
  request (credits were exhausted on 2026-10-15).
- `mdl ci moodle-enrol_apply --matrix --behat`, every leg log read for `: FAILED`.

### K8. Documentation &mdash; written 2026-10-25

As built: the README's group key section says what each person is told, what the queue, the
review page and the reports show, and what `local_groupdist` adds and what happens without it;
the features list and "Data stored" name the key route. `CLAUDE.md` carries the in-lock
re-resolve (since K4) and now the `customchar1` choice.

- README: the setting, the key route, who is told, the new column, and `local_groupdist` as an
  **optional** dependency (what it adds, what happens without it); directory description in
  step. `version.php` does NOT list it in `$plugin->dependencies`: that would make it
  mandatory.
- `CLAUDE.md`: the "key is re-resolved inside the lock" invariant and the `customchar1` choice.
- This file's status line.

## Carried from the security read of K1/K2 (2026-10-23)

The security-diff-read over K1/K2 found nothing blocking: no output carries a key, the throttle
cannot lock another applicant, `resolve()` reads only the instance's own course. Three things
belong to the stages that wire the key in, and K4/K5 must close them:

1. **`\local_groupdist\seats::for_groups()` is unscoped** by design (the caller decides). Once
   anything an applicant sees depends on it, `groupseats` must be handed only the instance's
   own course groups, and a hidden group's "full" must not become a membership-count oracle.
2. **`resolve()` says which group; it does not say the group may admit.** The write door must
   check seats (`groupseats::full()`, under the group lock) after resolving, and record what it
   actually did (`keyoutcome`) only after the membership is written.
3. **`keyattempts::record_failure()` is a get-then-set**, so parallel guesses can overshoot the
   limit, and each account has its own five. Count only after a key has failed, clear only
   after an admission succeeded, and decide in K5 whether the count also needs a core lock or a
   per-network bound.

## Risks worth naming

- **Anyone who may manage groups can now mint admissions.** A key is set on the group page
  under `moodle/course:managegroups`, not under this plugin's `enrol/apply:config`. Today that
  is true of `enrol_self` as well; here it is mitigated by G2 (the teacher who configures the
  method opts in) and by the settings line listing the groups that carry a key.
- **Keys leak.** A group key is a shared secret handed out on paper and in chats. The
  manager notification (G8) and the report column are how a leak is noticed; rotating the
  key on the group page is how it is closed, and the in-lock re-resolve makes the rotation
  effective at once.
- **Seats move under you.** groupdist's distribution, a teacher's manual add and core's group
  import all write `{groups_members}` without this plugin's lock. The lock serialises key
  admissions against each other only; anything else can still take a group past its seats a
  second later. That is the same advisory standing seats have on groupdist's own bulk edit page
  (a red indicator, not a refusal), and it is why the review page reads the live count rather
  than trusting the one recorded at submission.
- **Two routes into ACTIVE.** Everything after activation must keep being shared through
  `activate()`/`complete_approval()`, or the key route will drift from the approval route the
  way the "Edit enrolment" route did before `hook_callbacks.php`.
