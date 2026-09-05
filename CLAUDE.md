# Claude instructions for `enrol_apply`

This file is auto-loaded as context whenever Claude works in this plugin's directory
tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding style, CI gates,
lang-string rules, the `mdl` environment, git rules) — do not repeat them here. This
file keeps only what is true for this plugin.

Plugin context: a Moodle **enrol** plugin ("Course enrol confirmation") that inserts an
approval step into course enrolment. A user applies, optionally leaving a comment and
filling in profile fields; the enrolment is created **suspended**; a manager then
confirms, defers to a waiting list, or cancels it. It owns two tables —
`enrol_apply_applicationinfo` (the per-application comment, keyed by
`user_enrolments.id`) and `enrol_apply_groups` (groups an approved applicant joins) —
and leans on core's enrolment expiry machinery for everything time based. Supports
Moodle **5.1 through 5.2** (`$plugin->requires = 2025100600`,
`$plugin->supported = [501, 502]`). CI is the moodle-an-hochschulen reusable workflow,
one job per supported branch in `.github/workflows/ci.yml` — **update those jobs when
`supported` changes**. Development happens on the m501/m502 stacks; this repo mounts at
`enrol/apply` (see `~/dev/moodle-dev/plugins.conf`).

## Agent orchestration budget (fleet rule, repeated here on purpose)

This is section 6 of `~/dev/CLAUDE.md`, mirrored into every repo of the fleet.
It is the one fleet rule these files are allowed to duplicate: a session opened
inside a plugin directory does not always carry the fleet file in context, and
the cost of missing this rule is paid immediately, in tokens, before anyone
notices it was missing.

**Every `Agent` call and every `agent()` inside a Workflow sets `model`
explicitly.** An omitted `model` runs that subagent on the session model — the
most expensive one — and is a defect, not a default:

- `sonnet` — readers, graders, refuters, verifiers, measurers, stale-reference
  sweeps, mechanical renames, test files written against a stated contract.
- `opus` — implementers of non-trivial code, ADR and documentation drafters,
  consolidators, critics, estimators.
- the session model — only for work done inline in the main loop, never for a
  subagent.

Multi-agent workflows stay opt-in and lean whatever mode is on: size the fan-out
to the question (roughly 10 to 25 agents), one refuter per finding and only for
blocking findings, no open-ended "investigate every gap" rounds. Stop and resume
with `resumeFromRunId` rather than relaunching, so completed agents stay cached.
State which model each role got when reporting a launch.

Measured 2026-09-02 on the hub category-context gap analysis: 7 lenses x 2
refuters x 2 measurers plus a critic round, every one of them on the session
model, had to be interrupted for cost — 36 agents with the refuters on Sonnet
produced the same verified result. The rule has been restated three times
(2026-09-01, 2026-09-02, 2026-09-04), the last time over implementers launched
without `model` while the reviewers around them were correctly downgraded.

## Origin

This is a fork of [emeneo/moodle-enrol_apply](https://github.com/emeneo/moodle-enrol_apply).
Up to commit `867e248` the fork was byte-identical to upstream; everything after that is
ours. Upstream had accumulated several half-applied merges, and a good part of the work
here was finishing or removing them — when something looks arbitrary, check the upstream
history before assuming it was deliberate.

## Commands

```sh
mdl ci moodle-enrol_apply                # full CI locally before any push
mdl phpunit m502 enrol_apply             # targeted tests
mdl behat m502 @enrol_apply              # Behat smoke tests
mdl grunt m502 enrol/apply               # rebuild amd/build (commit with src)
mdl purge m502                           # after template or renderer changes

mdl mutate moodle-enrol_apply mutations/gates.conf --dry-run   # patterns only, seconds
mdl mutate moodle-enrol_apply mutations/gates.conf             # the mutation sweep
```

**`mutations/gates.conf` pairs a hundred guards with the test each one must redden**, and it is
the executable form of a claim this repository makes constantly in prose. Add a mutation in
the same change as the guard it protects; a guard whose mutation reddens nothing is held by
no test, whatever its comment says. `mutations/README.md` has the rules, including the two
that have already cost time: perl interpolates `$variables` on BOTH sides of `s///` so a
pattern naming `$manager` matches nothing, and `lib.php` carries the same
`has_capability(...)` line twice, distinguished only by the `return` that follows it.

## Code layout

```
lib.php                      enrol_apply_plugin: the whole state machine
classes/form/application_form.php  what the applicant fills in, in a modal or on apply.php
apply.php / applied.php      the no-JavaScript transport and the acknowledgement
edit.php / edit_form.php     per-course instance configuration
manage.php                      the approval queue and its bulk actions
classes/table/applications.php  the queue's table, a core_table\dynamic one
renderer.php                 page rendering plus the notification e-mail body
templates/                   manage, manage_actions, review, review_actions, decision_controls,
                             application_navigation, application_notification, profile_offer
classes/hook_callbacks.php   reconciles approvals made outside confirm_enrolment()
classes/local/applicantstate.php  what an applicant is told about their OWN application
classes/local/applications.php  mentee lookup shared by the queue
classes/local/capacity.php   the two capacity numbers, and how many deferrals hold one
classes/local/submission.php the durable application record: constants, writes, reads
classes/observers.php        course_deleted, orphan cleanup for the plugin-disabled case
classes/privacy/provider.php full provider: two tables, two personal-data roles
classes/task/                sync_enrolments, send_expiry_notifications, purge_submissions
backup/                      group mappings, comments and the durable trail, see below
```

## Architecture gotchas

- **`user_enrolments.status` carries a third value.** `ENROL_APPLY_USER_WAIT = 2` means
  "on the waiting list". Core only knows 0 (active) and 1 (suspended) but tests
  `status != ENROL_USER_ACTIVE` everywhere, so the extra value is inert to core and
  visible only to this plugin. Any query filtering applications must use
  `status != ENROL_USER_ACTIVE`, never `status = ENROL_USER_SUSPENDED`, or deferred
  applications vanish from the queue.

- **An applicant is told about their OWN row first, on all three surfaces, by one describer.**
  `\enrol_apply\local\applicantstate` exists because the enrolment page's panel, `applied.php`
  and `application_form::check_access_for_dynamic_submission()` each branched on nothing but *does
  a row exist* and so read the PENDING wording to everybody — a deferred applicant was told a
  decision nobody had taken was still pending, and so was somebody approved onto an enrolment that
  grants no access.

  **Four states, not three, and ACCESS is a parameter rather than something read off the row.**
  An ACTIVE row can grant access or grant none, and only the second may say the enrolment is not
  active: `applied.php`'s gate asks for an application and nothing more, so a fully enrolled
  participant who keeps the link opens it legitimately, and a describer that read ACTIVE as
  "approved with no access" told a working participant their enrolment was broken and sent them to
  bother their teacher. The row alone cannot tell the two apart — core pairs the status with the
  enrolment's window AND the enrol instance's own status — so each caller passes what it knows,
  which is `is_enrolled(..., onlyactive: true)` for two of them and a guaranteed false for the
  enrolment page, whose panel core only renders to somebody it has already refused.

  Two more consequences worth keeping. **The applicant's own row must be tested BEFORE
  `allow_apply()`, on the form as well as on the page**, or a method that stops accepting
  applications tells everybody who already applied "Enrolment is disabled or inactive" — a message
  about somebody else's problem. And the form's refusal takes `message_key()` rather than
  `describe()`, because `moodle_exception` wants an identifier: that is the one place the
  state-to-wording mapping is allowed to be read as a key, and it is still a `match` over literals
  rather than a computed id.

- **The decision methods reconstruct a missing record before they write to it.**
  `record_outcome_message()` and `record_decision_note()` both loop over the rows they find and
  write nothing when there are none, in silence — so a decision on an application older than
  `enrol_apply_submission`, or made through any route that never wrote one, stored the operator's
  message nowhere and mailed it nowhere while the status looked perfectly correct.
  `submission::ensure()` closes that. It reconstructs only what a pending application can honestly
  claim: the comment from `enrol_apply_applicationinfo`, the enrolment's OWN `timecreated` (not
  this moment — `prior_applications()` orders by it and the retention sweep filters on it), an
  empty snapshot, and `STATUS_PENDING` whatever the enrolment is doing, because the caller stamps
  the real decision immediately afterwards.

- **Correcting a deferral's reason is not a second decision, and `wait_enrolment()` has to know
  the difference.** Admitting already-deferred rows is what lets the reason be edited at all, and
  it is what makes everything the first deferral did run a second time. Two guards keep it
  honest, both keyed on one `$moved` read BEFORE the update (afterwards every row is deferred,
  whether it arrived just now or last month): `decide()` is passed `$moved` rather than `true`, so
  its own guard leaves the ORIGINAL decider and date alone; and the applicant is notified only
  when the enrolment moved **or** the decider typed a message for them. The second half is not
  symmetry — a message written into a box whose whole purpose is to reach somebody must not be
  silently dropped. Gates `BI` and `BJ`.

- **`decisionnote` is the decider's, `outcomemessage` is the applicant's, and nothing may blur
  them.** The note is never mailed and appears on no applicant-facing page; it is read by the
  review page, the report and the privacy export. Both writers CLEAR on empty, which is not
  tidiness: without it a re-queued application — which core's "Edit enrolment" screen and an
  `expiredaction` of *suspend* both produce — is decided a second time carrying the first
  decision's reason with nothing on screen to say so. For the same reason the review page SHOWS
  the stored note but never pre-fills the box with it.

- **The notification wording falls back at READ time, in `notify_applicant()`.** All six settings
  ship empty and a `settings.php` default cannot reach an existing site:
  `admin_apply_default_settings(NULL, true)` runs only from `install_core()` and skips any setting
  whose `get_setting()` is not null. The subject and the body fall back independently, and they
  want **opposite spellings** of the course name — the body is HTML and goes through
  `update_mail_content()`, which escapes what it substitutes; the subject is not HTML and is
  escaped again by the message sink, so it takes the plain spelling. Still unfixed and recorded
  rather than half-done: every applicant notification is composed in the DECIDER's language.

- **`?userenrol=` is a page, not a narrower queue, and it is tested before `id=` for that
  reason.** It used to render the queue's table filtered to one row, bulk bar and all,
  and it required the capability in the applicant's own USER context and nowhere else — which
  made it a mentor's page by accident. Measured on both branches: a teacher holding
  `enrol/apply:manageapplications` in the course an application was made to fails that check, so
  opening one application threw at them. The gate is now
  `\enrol_apply\local\queue::require_review_access()`, which applies the plugin's own
  `can_manage_application()` — the same predicate every decision applies to every row, so the
  people who may act on an application are exactly the people who may look at one. Nothing new
  is disclosed: a course teacher already sees every one of those applications, with the same
  fields, on the queue.

  `require_login($course)` is deliberately still not called on that path. A mentor holds no
  course access at all, which is the whole point of that delegation level.

  **The review form posts the queue's own contract** — `formaction`, a non-empty
  `userenrolments[]`, `sesskey` — so `manage.php`'s handler needs no branch of its own and every
  guard it applies to a queue decision applies here unchanged. Keep that contract if either
  surface is rebuilt.

  **A decision made there does not redirect back to it.** Two of the three decisions leave that
  url reviewing an application which is no longer awaiting one, so landing on "nothing to
  decide" having just decided it reads as a failure. Deferring is the exception and is sent to
  the same place anyway, because one decision landing somewhere else than the other two would
  be stranger than all three landing on the queue.

  **Which queue is chosen by `queue::scope()`, and it answers TWO questions that must never
  disagree** — where a decision sends the operator back to, and which applications the
  previous/next links walk. It is derived from what the operator may open, never from the
  request: `manage.php` tests `userenrol` before `id`, so on the review path `$id` is read into
  a variable and then never authorised and never used. A walk built on it would let a request
  parameter choose which applications are enumerated, and the plan for this navigation said to
  carry the scope exactly that way, on the belief that `manage.php` had already authorised it.

  The three scopes are `can_manage_application()`'s three levels in the same order — the
  instance queue when the operator may open it, the site-wide queue for a system grant, the
  mentees otherwise — which is what makes every application the walk can reach one the operator
  may decide, **by construction rather than by a per-candidate check**. `mod_book`'s
  skip-the-candidates-that-fail loop is therefore deliberately absent; a test per scope holds the
  property instead.

  **The access test is `can_access_course($course, null, 'enrol/apply:manageapplications', true)`
  and all four arguments are load bearing.** Two separate defects lived here, and the second one
  hid behind a confident sentence about the first.

  Dropping the CAPABILITY: with the bare `can_access_course($course)` that stood here first, a
  mentor enrolled in the course as anything at all satisfied it, was redirected to
  `manage.php?id=` after deciding, and was refused by its `require_capability()` — the decision
  taken and applied, reported as an exception.

  Dropping `$onlyactive`: the three-argument form was then documented as "the pair
  `manage.php?id=` itself demands". **It is not.** `can_access_course()` defaults `$onlyactive`
  to false and reaches `is_enrolled()` with it, so a **suspended or expired** enrolment counts as
  access, while `require_login($course)` refuses both. Measured on 5.1 and 5.2 over five
  operators — active teacher, suspended teacher, expired teacher, category manager, unenrolled
  category teacher — the four-argument form agrees with `require_login()` on every one; the
  three-argument form disagrees on the suspended and the expired one, who kept the capability,
  decided, and were bounced to `/enrol/index.php`. A role assignment survives a suspension, which
  is what makes that operator both legitimate and broken.
  `test_a_mentor_enrolled_in_the_course_still_walks_their_mentees` and
  `test_a_teacher_whose_own_enrolment_is_suspended_opens_no_queue` pin the two halves.

  **The scope must CONTAIN the application it was derived for**, which is a second property and
  not the same one. `neighbours()` compares the anchor's `(timecreated, ue.id)` against the scoped
  set, so anchored outside it the page offers insertion-point neighbours — a "next" in another
  course, and no link back to the application on screen. The mentee branch used to be taken
  whenever the operator mentored *anybody*, so `scope()` now takes the application and tests
  membership. The first two branches contain it by construction.

  **An operator can legitimately be able to open NO queue**, and that case is reachable rather
  than defensive. The plainest route is the capability held at a course context through a category
  role by somebody not enrolled — **on a visible course**; hiding it is one sufficient condition
  among several, not the mechanism, and an earlier version of this bullet said it was. A teacher
  whose own enrolment is suspended lands here too, and so does a mentor looking at an application
  none of their mentees made. They are sent to `destination::home_page_url()`, because both queues
  would refuse them. `neighbour()`'s empty-mentee early return is that branch's other half:
  `get_in_or_equal()` throws on an empty array, and
  `test_an_application_outside_the_mentees_gives_no_walk_at_all` is what holds it.

  **The group and role choosers are gated on the COURSE capability, which is stricter than the
  page.** A mentor reaches the page through the applicant's user context and holds nothing in
  the course, and `groups_get_all_groups()` applies no capability check of its own — unlike
  `get_assignable_roles()`, which self-gates and would have come back empty for them anyway. So
  without that gate the page listed every group name in a course the reader cannot open. The
  instance's own groups and role still apply to their decision.

- **Slice U2 narrowed the bullet below, and the headline no longer holds as written.** The
  snapshot is still read frozen and still never re-resolved. But the page now carries an identity
  line, which DOES read the live profile - through `\core_user\fields::get_identity_fields()` and
  `for_identity()->get_sql()`, for the fields the site named and this reader may see, and never
  from a stored snapshot key. The distinction is the whole point: what was fixed was handing an
  archive-controlled key to a live column with no allowlist, not the idea of reading a profile at
  all. Read the bullet with that in mind.

- **The review page's profile snapshot is read frozen, and NOTHING IN THE SNAPSHOT PANEL reads the live
  profile.** It comes from `submission::read_snapshot()` and never from `diff::compute()`, which
  re-resolves the field set from the LIVE instance and re-classifies it against the current user
  — so a field the teacher has since stopped asking for silently vanishes from a record of what
  was submitted. The snapshot's own stored labels are used for the same reason.

  **The live read is a security boundary, and the first version of this panel got it wrong.** It
  showed "what the profile says now" beside each row by handing the stored key to
  `fields::current_value()`. That key comes out of `userinfodata`, which
  `restore_enrol_apply_plugin` writes **verbatim from a foreign archive**, and `current_value()`
  dereferences whatever `{user}` column an `s_` key names with no allowlist — the `DENY` list
  that keeps `s_password`, `s_secret`, `s_email` and `s_idnumber` out of this plugin governs the
  WRITE path only. Measured on m502 with a crafted envelope: the panel rendered the applicant's
  **password hash**. The `c_<id>` branch reads `{user_info_data}` directly, past every
  `PROFILE_VISIBLE_*` gate core applies on its own profile page. And the masking above does not
  help: `visible_keys()` returns `ALL_FIELDS` for any teacher or manager, so the key filter is
  skipped entirely and nothing has been "judged visible" at all — which is exactly what the
  comment justifying the read claimed. The Report Builder surface never reads the live profile
  either; that is now true of both doors onto this record.

  Masking is `reportbuilder\local\formatters\submission::visible_keys(context_course::instance(...))`,
  the report's own rule, so the two surfaces cannot disagree. **It costs a mentor the identity
  fields** — they hold nothing in the course — which is the stricter reading of a real question
  rather than an obviously right answer. Withheld rows are dropped whether or not they hold a
  value: a marker appearing only where there is data is a presence oracle. The masking governs
  the PANEL, not the page. **That last sentence used to read "the applicant's e-mail address has
  its own row and always did", and slice U2 reversed it (2026-08-31, owner's decision):** the
  address is now one identity field among the rest, so a site whose `showuseridentity` does not
  name it does not show it here at all. The page DOES read the live profile now, through
  `\core_user\fields` and for identity fields only - which is a different thing from the stored
  snapshot key that once reached a live column with no allowlist, and is why that read is gated by
  core's own helper rather than by this plugin's.

  **Field keys are `s_<column>` and `c_<id>`, not `standard:<column>`.** Build them with
  `fields::standard_key()`. A hand-written prefix fails two ways at once and neither is loud:
  the masking list matches nothing, so under a restricted reader **the row silently vanishes**,
  and under an `ALL_FIELDS` reader it renders while any live lookup for that key returns nothing.
  An earlier version of this bullet said "the row still renders" of both, which is wrong in the
  reassuring direction.

- **The previous/next walk is SQL, one `LIMIT 1` per direction, and it is pinned in order and in
  set.** `queue::neighbours()` runs the queue's own predicate plus the scope clauses with a
  strict comparison against `(timecreated, ue.id)` — the unique final key #33 put on the
  listing's ORDER BY, needed here for the same reason and needed *more*: without it "later than
  this timestamp" cannot move within a tied group at all, and `enrol_user()` stamps whole
  seconds, so a cohort admitted by one script is one tied group. Measured on the live 5.2 site,
  three pending applications already share a timestamp and the walk steps through all three.

  **`:t` twice is `duplicateparaminsql`.** The comparison needs the timestamp in two places, so
  it binds two NAMES to one value; `fix_sql_params()` counts occurrences.

  **The walk does NOT honour the initials bar, and that is a decision.** The queue renders with
  `out(50, true)` and `query_db()` appends `get_sql_where()` — `firstname LIKE 'x%'` — so an
  operator who has picked a letter is looking at a narrower set than the predicate describes.
  Three measurements settled it. Turning the bar off would NOT close the gap:
  `set_initials_preferences()` runs from `setup()` whatever the `$useinitialsbar` argument says,
  and only `get_initial_first()` consults `use_initials`, so a stale preference or a crafted
  request parameter still filters — the control disappears, the filter does not. The load-bearing
  fact is that `get_sql_where()` never consults `use_initials` at all, only `prefs['i_first']` and
  `prefs['i_last']`; an earlier version of this bullet said "only `get_initial_first()` consults
  `use_initials`", which is wrong three ways over — `get_initial_last()` and
  `print_initials_bar()` read it too, and none of them is the filter. The preference
  itself lives in `$SESSION->flextable['enrol_apply_manage_table']`, **not** in a user preference
  (that key is `\enrol_apply\table\applications::UNIQUEID`, kept verbatim through the move to
  that class so no operator's stored sort was discarded):
  `flexible_table::$persistent` defaults to false on both branches and this table never calls
  `is_persistent(true)`. And honouring it would make the page depend on session state it does
  not render, so a bookmarked or emailed review link would lose its neighbours because of a
  letter clicked days earlier — invisible, which is the failure mode this repo treats as the
  defect. Not honouring it fails visibly instead, because the link names the applicant.

  **Test the walk against the LISTING, not against a hand-written expectation.**
  `test_the_walk_visits_exactly_what_the_queue_lists_and_in_its_order` walks from one row to both
  ends and compares with the queue table's own rows. Sharing code between the two
  would only make them agree on whatever that code said; this asserts the behaviour, so a scope
  clause, a join or an order that drifts on either side reddens it.

- **Copying core markup copies its class names, not their meaning.** The navigation is shaped on
  `mod_book`'s, and `btn-previous`/`btn-next` are defined **nowhere in core** — only in
  `mod/book/styles.css` under `.path-mod-book` (measured: zero occurrences in Boost's compiled
  sheet). On another page they style nothing while reading as though they carried mod_book's
  behaviour, so they are deliberately not used here. What they carry *there* is the one rule that
  matters: **a right-to-left flip for the chevrons, which core does not apply to any icon but
  `fa-question`.** `core_rtlcss` mirrors the layout, so "previous" correctly moves to the right
  edge in an RTL language while its arrow keeps pointing left — the arrow ends up meaning the
  opposite of the link. This plugin's `styles.css` carries that rule on its own wrapper class.
  Nothing here renders CSS, so it is verified by reading the cascade, like the sticky-footer
  polyfill, and says so.

- **`renderer_base::render()` resolves a `templatable` from its CLASS NAME**, so a
  `render_<class>()` method on the plugin renderer is not what makes the template render.
  Measured: renaming `render_application_navigation()` under the whole suite reddened **nothing**
  — `plugin_renderer_base::render()` finds no method, falls through to `$this->output->render()`,
  and core guesses `enrol_apply/application_navigation` from the namespace and class. The method
  was deleted rather than kept with a corrected comment: its only claim was one no test here
  could hold. What IS load bearing is the class-name-to-template-name coupling — renaming the
  template errors both rendering tests, which is the mutation that replaced the one that held
  nothing.

- **TWO capacity numbers, both in `\enrol_apply\local\capacity`, and confusing them is the
  standing hazard.** `applicant_limit`/`applicants`/`applications_closed` read `customint3` and
  count **every** non-expired row — pending, deferred and approved — because each of those people
  is in the pipeline. `places`/`places_taken`/`places_full` read `customint4` and count **ACTIVE**
  rows only. The gap between them is overbooking, which is the point: approval is discretionary,
  so a method sensibly accepts thirty applications for ten places.

  The two counts are written out separately, with their own full parameter arrays, on purpose. A
  single method taking a status flag is how they get composed — and `fix_sql_params()` tolerates
  surplus named parameters, so a half-refactor sharing one array between them runs clean and
  reddens nothing.

  **`places_full()` blocks nothing.** It is advisory by decision: the manager is warned and
  decides, because a hard block would contradict the plugin's premise and would have to be
  reproduced on three routes, the per-row icon having no channel to explain a refusal at all. So
  `places_taken()` above `places()` is an ordinary state, not corruption — a restore or a lowered
  setting produces it directly, and the class must report it rather than clamp.

  **The warning is emitted where somebody is standing, never from `complete_approval()`**, which
  runs TWICE for a queue approval while `\core\notification::add()` does not deduplicate: a batch
  of ten would warn twenty times, and the earlier pass sees pre-write state anyway. It lives in
  `manage.php` before the redirect, and on the queue itself — the latter rendered **outside** the
  template's `hasrows` section, because an instance at its applicant limit has an EMPTY queue,
  which is exactly when the manager needs to be told why.

  **A deferred row counts against the applicant cap for ever, and the remedy is DATA rather than
  the predicate.** `applicants()` has no status clause, `wait_enrolment()` writes `timeend = 0` so
  no arm of `process_expirations()` reaches the row, and `get_unenrolself_link()` demands
  `ENROL_USER_ACTIVE` so the applicant cannot withdraw. `capacity::deferred()` makes the number
  visible — on the review page's capacity panel and in the queue's applications-closed notice —
  and cancelling those rows is what frees the room. **Excluding them from `applicants()` is
  forbidden**: it changes what `is_full()` ANSWERS for the three plugins that reach it through
  `is_callable()`, and gate `AC` proves only that the method still delegates.

  `deferred()` needs a `require_once` of this plugin's `lib.php` inside the method, because
  `ENROL_APPLY_USER_WAIT` lives there and `classes/local/` is autoloaded while `lib.php` is not.
  Without it the method is a **fatal on first call**, not a wrong answer. Inside the method rather
  than at file scope, which is where the report formatter and the bulk operation already put the
  identical line: a require at file scope would drag in the `MOODLE_INTERNAL` guard the file
  correctly does without.

  **`enrol_apply_plugin::is_full()` keeps its name and fronts the APPLICANT question.** Renaming
  it breaks `local_dimensions`, `local_unlistedcourses` and `theme_boost_union_fundaseg`
  silently, since `is_callable()` would simply start returning false and each would fall back to
  its own unfiltered count.

  **The lang key changed but the config key did not.** `maxenrolled` → `maxapplicants` with a
  `lang/en/deprecated.txt` entry, because the string's *meaning* changed and a site that
  customised the old wrong label would otherwise keep it. `enrol_apply/maxenrolled` stays: a
  config key is data, and renaming it silently resets every site's configured limit.

- **Neither number is the queue predicate.** This is the most damaging confusion available in this plugin, so read it before
  touching either. `queue::awaiting_decision_where()` is `status != active AND not expired`: it
  answers *which applications still need deciding*. Capacity asks *which enrolments still hold a
  place*, and an approved, **active** learner holds one — exactly what the queue's status clause
  throws away. Borrowing that predicate, or indexing into the array it returns to lift the
  `timeend` half out, makes the cap exceedable by the number of approvals.

  And it fails **silently**: `fix_sql_params()` tolerates surplus named parameters
  (`lib/dml/moodle_database.php`, "there might be more params than needed"), so a half-refactor
  that passes the whole parameter array with only one clause runs clean and reddens nothing.
  `test_every_state_of_an_application_holds_a_place` is what catches it — the pending and
  waiting-list rows in that test pass either way, and only the ACTIVE one tells the two apart.

  It is also **not** core's access predicate. `enrol_get_all_users_courses()` and friends pair
  the expiry test with `timestart < :now`, and copying that half would free the place of somebody
  enrolled with a future start date. The line is *"will this row ever grant access again?"* —
  expired: no; not started yet: yes.

  **Why it excludes expired rows at all** is the ratchet: the plugin ships
  `expiredaction = ENROL_EXT_REMOVED_KEEP`, whose arm of `process_expirations()` is literally
  `// ENROL_EXT_REMOVED_KEEP means no changes.`, so an expired row survives for ever. Counted, it
  closed a course's applications permanently with an empty queue and nothing able to explain it.
  The cost is a deliberate divergence from core's own unfiltered `Users` column
  (`enrol/instances.php`) and from `enrol_self`'s cap — documented rather than hidden, because a
  teacher comparing the two screens will notice.

  **`limit()` must stay `> 0`, not `!== 0`.** `db/upgrade.php` writes `customint3 = null` on one
  path, and a negative would otherwise mean "permanently full" rather than "uncapped".

  **Three plugins outside this repo consumed the old inline count** — `local_dimensions`,
  `local_unlistedcourses` and `theme_boost_union_fundaseg` — and all three now delegate here
  behind a `class_exists()` guard, keeping the unfiltered count as the fallback because that is
  what an older `enrol_apply` build genuinely means. Their own tests seat occupants with
  `timeend = 0`, so they stayed green through the divergence and nothing in any pipeline
  reported it. If this predicate changes again, they change with it.

- **Deferring clears the expiry, and the literal `0` is load bearing twice.**
  `update_user_enrol()` gates each date on `isset()`, so `null` means "leave it alone" and there
  is no other spelling — which is how a once-approved application used to land on the waiting
  list still carrying a past `timeend`. That row is stranded by construction: core's SUSPEND arms
  filter `status = active`, which it fails, so no sweep touches it, and the queue excludes it by
  that same `timeend`, so no listing offers it. It waited for a decision nobody could take.

  It must be the **integer** `0`: core's `communication/classes/hook_listener.php` compares
  `timeend !== 0`, and `'0' !== 0` is true, so a string would drop the applicant out of the
  course communication room on any site running that subsystem.

  `wait_enrolment()` also sets `$userenrolment->timeend = 0` in memory, because the row was read
  before the update and `update_mail_content()` substitutes `{timeend}` for every message type —
  without it the wait mail announces the date that was just cleared. Both live templates are
  empty, so that one is a trap rather than a live defect, and `AB` in `gates.conf` holds it.

- **One SQL definition of "awaiting a decision", in `queue::awaiting_decision_where()`** — read
  by the approval queue, the submitted-comments listing, the review lookup and the retention
  sweep. It used to be written out in each of them, which is how the participants-page bulk
  decisions came to act on rows the queue excludes: two copies of a filter that is also a
  correctness boundary drift, and the one that drifted was the newer. Deleting the `timeend`
  half of it now reddens a test of the queue AND a test of the review lookup, which is the
  property the extraction buys.

  There is exactly one deliberate second expression of the rule and it is not SQL:
  `queue::is_awaiting_decision()` applies it to the `{user_enrolments}` rows core's participants
  page has already loaded and which never reach a query of this plugin's — the selection a bulk
  decision is given, and the one row behind each action icon. It sits next to the SQL rather than
  in `classes/bulk/`, where it used to live, because the second reader would otherwise have made
  it a third copy. Keep those two in step by hand; there is no third.

- **A stale review link is the ordinary case, not the edge one.** An application is decided
  exactly once and the url that reviewed it outlives the decision. `queue::application()`
  returns null for a decided application, a deleted enrolment and an id that never existed
  alike, and the page says one thing for all three — verified live on 5.2, where the two
  reachable cases render byte-identical pages. Before this, a deleted enrolment raised a raw
  `dml_missing_record_exception` and a decided one rendered an empty queue with no explanation
  at all.

  **Be careful what that merge is claimed to buy, because an earlier draft of this bullet
  claimed too much and three reviewers then argued the claim was fine.** It does NOT make the
  page silent about whether an id names a live application. Measured on 5.2 as a logged-in user
  with no claim on the course: `?userenrol=<nothing there>` renders the "no application" page
  with HTTP 200, while `?userenrol=<a pending one>` is refused by `require_review_access()` and
  comes back 500. So the page still answers "is user enrolment N a pending application?" — as
  every Moodle page that refuses by capability answers the same question about its own object,
  and the refusal names neither the applicant nor the course. What the merge buys is that
  nobody, entitled or not, can tell a decided application from a deleted one.

- **Authorisation is per row, not per page.** `manage.php` decides which scope you may
  open; `can_manage_application()` in `lib.php` re-checks every single user enrolment
  before acting on it. Three delegation levels are accepted — system, course, and the
  applicant's own **user context**, which is what lets a mentor approve for the users
  they mentor. Keep the per-row check even when the page-level check looks sufficient:
  the bulk form posts a list of arbitrary ids.

- **The mentee path is Moodle's mentor pattern, not groups.** With no `id` and no
  `userenrol`, the queue falls back to the users the current user holds a role assignment
  over *in those users' own contexts* — the same enumeration core uses in
  `blocks/mentees/block_mentees.php`. It has nothing to do with course groups: no query in
  this plugin has ever filtered the queue by group. That branch deliberately skips the
  system-level `require_capability` when it finds mentees; the table is then filtered to
  exactly those user ids. Do not "simplify" the skip away without also re-scoping the
  table, or you turn a mentor view into a site-wide one.
  The listing and the authorisation must stay in agreement: `can_manage_application()`
  authorises on the user-context capability, so the listing enumerates the same thing.
  The earlier cohort-based enumeration broke that agreement in both directions — it
  scanned every cohort peer on each request, and it hid mentees who shared no cohort even
  though the mentor could approve them by id.

- **Group membership follows approval, not application**, and is written with
  `groups_add_member($groupid, $userid, 'enrol_apply', $instance->id)`. The component
  argument is what lets core's `unenrol_user()` clean the membership up again
  (`lib/enrollib.php`, the block that deletes `groups_members` rows by component and
  itemid). Dropping it leaves memberships behind whenever the user has another
  enrolment in the course.

- **The ROLE follows approval too, and it is stamped even though `roles_protected()` is false.**
  `apply()` enrols with no role; `complete_approval()` assigns one, reading the decider's choice
  off the durable record and falling back to `$instance->roleid`. Three things about that pairing
  were measured on both branches and none of them is obvious:

  **The stamp is what makes core clean up.** `unenrol_user()` unassigns by component and itemid
  unconditionally, and `process_expirations()` has the same line ("remove all roles that belong
  to this instance and user"). Only `process_expirations()` guesses — `unenrol_user()` never
  reads `$instance->roleid` at all, and a bare assignment survives it for a different reason:
  the blanket sweep runs only when this was the user's last enrolment in the course. Where core
  does guess, it guesses `$instance->roleid` —
  and once a decider can choose a *different* role that guess is wrong by construction. Measured
  on m502, with an unrelated manual enrolment in the same course so this was not the applicant's
  last one: a Teacher chosen against an instance defaulting to Student **survived** the sweep
  under `expiredaction` of both unenrol and suspendnoroles when the assignment was bare, and was
  removed correctly under both when it was stamped.

  **`roles_protected()` staying false is what keeps it removable by hand**, and the reason
  recorded in the slice I handoff for not stamping — "Moodle's UI refuses to remove a role
  assignment owned by a component" — is **false for this plugin**. The refusal in
  `user/classes/output/user_roles_editable.php` (byte-identical on 5.1 and 5.2) is gated on the
  owning plugin's `roles_protected()`, so with it false the participants page removes a stamped
  `enrol_apply` assignment like any other. The one screen that does not is
  `admin/roles/assign.php`, which by design touches only `component = ''` rows.

  **`role_assign()` is idempotent on the whole tuple, component and itemid included.** Two passes
  computing the same role produce one row, which is what makes `complete_approval()` running
  twice safe. Two passes computing *different* roles produce two, which is why the role is read
  off the record rather than passed as an argument — and unlike the groups, nothing afterwards
  can tell which of the two this plugin meant.

  **`role_assign(0, ...)` throws**; it does not quietly do nothing. An instance can carry
  `roleid = 0` — the column is nullable with a default of 0, and a restore writes 0 whenever the
  archived role maps to nothing the restoring user may assign — so `assign_decided_role()` skips
  explicitly. Until this change `enrol_user()`'s own `if ($roleid)` was what swallowed it.

  **The stamp also has to be restored, and forgetting that lost the role in silence.** Core hands
  any `{role_assignments}` row whose component starts with `enrol_` to
  `enrol_plugin::restore_role_assignment()` (`restore_stepslib.php:2350`, the same line on both
  branches), whose base implementation is empty. That branch has **no fallback and writes no
  backup log line** — the generic-component branch beside it does both — so between the commit
  that stamped the assignment and the one that added the override, every restore and every course
  copy gave the applicant an ACTIVE enrolment and no role. Measured on 5.1 and 5.2 with three
  controls in one restore: a bare manual assignment, a bare assignment of the pre-stamp shape and
  an `enrol_self` one all survived, and only the apply row vanished. This is the identical
  mechanism `restore_group_member()` already documents for memberships; the role half was simply
  missed. `enrol_flatfile` is the precedent for the pairing — it stamps, its `roles_protected()`
  is false, and it overrides.

  **`role_assign()` performs no assignability check at all**, so the `get_assignable_roles()`
  allowlist in `confirm_enrolment()` is the only thing between a posted `roleid` and a role
  assignment. Compare with `array_key_exists`, never `in_array`: the values are localised names.
  The *fallback* is deliberately not allowlisted — it is what every application has been given
  since the plugin was written, and filtering it would silently stop an instance configured with
  a role its teacher may not assign from granting anything.

- **`confirm_enrolment()` is not the only way an application becomes active.** Core's
  participants page offers "Edit enrolment", which posts to `enrol/editenrolment.php`;
  that page requires only `enrol/apply:manage` and drives
  `enrol_plugin::update_user_enrol()` directly, so it knows nothing about groups or the
  application row. `classes/hook_callbacks.php` observes
  `\core_enrol\hook\before_user_enrolment_updated` and calls `complete_approval()`
  whichever route was taken. Put any new post-approval side effect in
  `complete_approval()`, never inline in `confirm_enrolment()`, or the two paths drift.
  The observer **does** notify, and the sentence that used to stand here said the opposite.
  `complete_approval()` queues `\enrol_apply\task\notify_approval` (`lib.php`), which is
  reached from both routes; queueing is deduplicated on classname, component and custom data,
  so the two callers cannot produce two messages. It is exactly the kind of sentence a
  reviewer leans on, which is why it is called out rather than quietly corrected.

- **`enrol_plugin::cron()` is dead.** Core declares it empty and nothing calls it. The
  `expiredaction` setting only works because `classes/task/sync_enrolments.php` calls
  `process_expirations()` on a schedule. If expiry stops working, look at the task, not
  at `lib.php`.

- **Never put a `timeend` on a pending application.** `apply()` enrols with
  `timestart = 0, timeend = 0` on purpose; `confirm_enrolment()` stamps the real period on
  approval. The reason is not tidiness: the `ENROL_EXT_REMOVED_UNENROL` branch of
  `enrol_plugin::process_expirations()` (`lib/enrollib.php`) selects on
  `timeend > 0 AND timeend < now` **with no status filter**, so a pending or waiting-list
  row carrying an expiry gets unenrolled instead of decided. This only became reachable
  once `sync_enrolments` started running, which is why upstream never saw it.
  `tests/lib_test.php::test_pending_application_is_not_reachable_by_the_expiry_sweep`
  pins it.

- **A capability declared at one context level is checked at another.**
  `enrol/apply:manageapplications` is declared `CONTEXT_COURSE` in `db/access.php` but is
  also evaluated against the applicant's user context for the mentor path. That works at
  runtime, but the capability does not appear on the user-context permission screens, so
  the delegation has to be configured through a role whose context levels include user.
  Resolving this properly means either a second capability declared at `CONTEXT_USER` or
  dropping the mentor path; it is an open product decision, not an oversight.

- **Do not cache the mentee list in MUC.** The candidate set is now bounded by actual
  mentor role assignments, so the per-candidate `has_capability()` is cheap and the
  remaining cost is one indexed query per request. Caching it would trade that for a
  correctness problem with no clean invalidation point: the answer depends on role
  assignments, role capabilities and context overrides, and a stale entry keeps a revoked
  mentor approving. `has_capability()` is deliberately kept instead of joining
  `role_capabilities`, because only it honours overrides, prohibits and the admin bypass.

- **The backup classes live in `backup/moodle2/`, not `backup/`.** Core resolves them as
  `<plugin>/backup/moodle2/backup_<type>_<name>_plugin.class.php`
  (`backup/util/plan/backup_structure_step.class.php`). Upstream had them one directory
  up, so they were silently never loaded: no error, no warning, just an `enrolments.xml`
  with no plugin element in it. If plugin data stops appearing in a backup, check the
  path before reading the code.

- **A restored course carries a live approval queue — with comments only if the backup
  had users.** Core restores every `user_enrolments` row through
  `restore_user_enrolment()`, and this plugin passes `$data->status` straight through, so
  `ENROL_USER_SUSPENDED` and `ENROL_APPLY_USER_WAIT` rows come back pending whatever the
  plugin does. The comments are keyed by `user_enrolments.id`, for which core registers no
  mapping, so `restore_user_enrolment()` registers one (`enrol_apply_userenrolment`) that
  `restore_enrol_apply_plugin` then resolves. That works because core writes
  `<user_enrolments>` into the enrol element before `add_plugin_structure()` appends the
  plugin's own data; if that order ever changes, `get_mappingid()` returns false and the
  comment is skipped rather than mis-attached. `tests/backup_test.php` pins the whole
  round trip — and note it needs `MODE_SAMESITE` plus an explicit unzip, because
  `MODE_IMPORT` produces no `enrolments.xml` at all.

- **The notification e-mail must not hardcode profile fields.** `icq`, `skype`, `aim`,
  `yahoo` and `msn` were removed from the user table in Moodle 4.0 and reading them cost
  five warnings per notification. `renderer.php` iterates `STANDARD_USER_FIELDS` and
  skips anything the form did not submit, which also covers fields a site has hidden.

- **The queue's selection is core's `checkbox_toggleall`, and two things about it are not
  obvious.** The header checkbox, every row checkbox and the bulk action share the one group
  named by `\enrol_apply\table\applications::TOGGLE_GROUP`. Targets are matched by PREFIX and the action
  element by an EXACT string, so a mismatch disables nothing and reports nothing — which is why
  `tests/renderer_test.php` asserts the group literal in all three places rather than reading the
  constant.

  **Core never sets the action control's initial state.** `checkbox-toggleall`'s `init()` binds
  two delegated click handlers and nothing else, so a control is live until the first click;
  every core caller closes that by hardcoding `disabled` in the server markup. This plugin does
  not, and `amd/src/manage.js` does it on init instead — the queue is operable without
  JavaScript, and an attribute only JavaScript can clear would remove a working path to buy an
  affordance only JavaScript users see. **That holds only because `styles.css` polyfills the
  footer's visibility.** Core parks a sticky footer at `bottom: calc(<height> * -1)` and slides
  it in by adding `hasstickyfooter` from `theme_boost/sticky-footer.js`, so moving the bar there
  without that rule would paint the queue's only submit control off screen for exactly the
  operator the enabled control was left enabled for. The rule is gated on
  `body:not(.jsenabled)`, which core writes from
  `lib/classes/output/requirements/page_requirements_manager.php`. Nothing in this repository
  renders CSS, so that pair is verified by reading the cascade and not by a test. That module now does nothing else, and the reason is worth keeping: core
  has no `indeterminate` handling anywhere, so the header checkbox lost its tri-state, and the
  first attempt to keep it — subscribing to `core/checkbox-toggleall:checkboxToggled` — shipped a
  runtime error that **phpcs, eslint, grunt and Behat all passed**. `core/pubsub` has named
  exports and no default, so `import PubSub from 'core/pubsub'` compiles to `_pubsub.default`,
  which is `undefined`; core's own ES importers write `import * as PubSub`. Nothing in this
  plugin's pipeline executes its JavaScript, so anything in that module has to be either
  observable from Behat or not written.

  **The sticky footer is NOT relocated in the DOM**, unlike `core/modal`. Its position is CSS,
  so it is rendered inside `<form id="enrol_apply_manage_form">` and its controls post normally;
  core does the same in `grade/templates/edit_tree.mustache`. Only the ACTION belongs in it — the
  bar is a fixed 80px box whose `.sticky-footer-content` carries `overflow: hidden`, so the
  message textarea and the two choosers stay in the page body. And `sticky_footer::add_classes()` builds
  its concatenation and then assigns over it, so it replaces rather than appends: pass every
  class through the constructor.

- **The participants page offers the same three decisions in bulk, and core gates that route with
  nothing at all.** `user/action_redir.php` contains no `require_login()` and no
  `require_capability()` anywhere in its bulk branch — byte-identical on 5.1 and 5.2, its only
  gates being `confirm_sesskey()` and a check that the plugin is enabled site wide. So
  `get_bulk_operations()` returning an empty array is what *refuses* an operator, not merely what
  hides the menu: core looks the chosen operation up in exactly that array and throws
  `errorwithbulkoperation` when it is absent. The capability is checked at the course context,
  which is deliberately stricter than `can_manage_application()` — a mentor holding it only in an
  applicant's user context is offered nothing here, and `manage.php` is what serves that scope.

  **`process()` re-checks it, and that second check is not the unreachable-guard pattern.** It is
  the gate for any driver other than core's, because the method is public and
  `enrol_bulk_enrolment_operation` declares it abstract with no gate in front of it.
  `test_process_refuses_an_operator_without_the_capability` holds it by calling `process()`
  directly, which is the only way to reach it.

  **The bulk path adds no per-ROW authorisation of its own, and that is the point rather than an
  omission.** It inherits `can_manage_application()` from inside `confirm_enrolment()`,
  `wait_enrolment()` and `cancel_enrolment()` — which is the whole argument for delegating.
  Measured on both branches: through core's dispatch a per-row check could not refuse anything
  anyway, because the menu gate tests the same capability at the same course context that
  `can_manage_application()` reaches second, and the manager is built for that one course. What
  the inherited check does protect is a selection reaching *beyond* the course — and that shape is
  NOT what a forged post produces, which an earlier draft of this paragraph claimed. Core builds
  the manager with the instance filter and `get_users_enrolments()` selects on `ue.enrolid`, so a
  posted id belonging to another course simply does not come back; core warns per dropped user and
  redirects when nothing is left. The cross-course shape reaches `process()` only from a caller
  other than core's driver — which is exactly the standing the second capability check already has,
  and exactly what `test_an_application_in_another_course_is_not_decided` builds, by composing two
  managers by hand.
  The slice plan's `test_bulk_confirm_re_authorises_every_posted_id` was specified against the
  belief that the bulk path needed a check of its own. Written that way it would pass by doing
  nothing.

- **The same page also carries a per-row decision icon, and `get_user_enrolment_actions()` is the
  one extension point here with no precedent for the OVERRIDE.** Measured on 5.1 and 5.2: no enrol
  plugin in core overrides it, and the base implementation is byte-identical on both branches. That
  is a claim about overrides only — **eight** core enrol plugins ship a
  `test_get_user_enrolment_actions()`, so the TEST has ample precedent and
  `enrol/manual/tests/lib_test.php:493` is the shape this plugin's follows. An earlier draft of
  this bullet said "no precedent at all", and the test file repeated it as "core has no PHPUnit
  coverage of this extension point" — written in a session that had already read one of the eight.

  **The link carries no `data-action`, and the reason first written down was false.** It said
  `user/amd/src/status_field.js` claims "exactly three action names" — `editenrolment`, `unenrol`,
  `showdetails` — so any other value is inert. **Two** modules claim names in this markup, because
  the participants table is a `core_table\dynamic` table inside
  `[data-region="core_table/dynamic"]`: `core_table/dynamic` adds a document-level click handler
  matching `a[data-action="hide"]`, `a[data-action="show"]` and `[data-action="showcount"]`
  anywhere inside that region (`lib/table/amd/src/local/dynamic/selectors.js:31-48`, identical on
  both branches). Six values would be hijacked, not three - all three of the second list are
  dispatched with `e.preventDefault()`, `showcount` included - from two lists that can grow
  independently. Carrying none is what makes it an ordinary link. The override appends to
  `parent::get_user_enrolment_actions()`, and `test_the_icon_is_added_to_cores_own_actions` keeps a
  future edit from silently taking core's Edit and Unenrol away from every apply row — note that
  what core builds those from is `allow_manage()` and **`allow_unenrol_user()`**, the latter only
  delegating to this plugin's `allow_unenrol()`.

  **It points at `manage.php?userenrol=` because the icon decides ONE application and `?id=` opens
  the whole queue**, and `docs/design/audit-trail-analysis.md` instructs the opposite because it
  predates the review page's gate. That document says the icon "must point at
  `manage.php?id=<enrolid>` and **not** `?userenrol=`", true of the user-context-only gate a course
  teacher fails and not of `queue::require_review_access()`. What is **not** a reason, though it
  was written down as one, is that an `id=` link "would have to pick an instance": `$ue` carries
  `enrolid`, so it could always have named the right one.

  **Two gates, and the capability one is deliberately not `can_manage_application()`.** It is read
  in the course, exactly as `get_bulk_operations()` reads it, so the icon and the bulk menu answer
  the same question on the same page and a mentor is offered neither; `manage.php` with no
  parameter is what serves that scope. Only `test_a_mentor_is_offered_no_icon_in_the_course` holds
  that — measured, every other test in the file stays green when the gate is widened to
  `can_manage_application()`, because none of the others creates a user-context assignment.

  The status gate is `queue::is_awaiting_decision()`, and its second clause bites only under a
  **non-default** `expiredaction`: the plugin ships `ENROL_EXT_REMOVED_KEEP`, under which
  `process_expirations()` changes nothing and a lapsed enrolment stays ACTIVE, excluded by the
  first clause and rendered by core as "Not current" rather than "Suspended". It also does one
  thing core's column cannot: core pre-sets "Active" and switches on 0 and 1 with no default arm,
  so a waiting-list row gets a green Active badge. **That value is this plugin's own** —
  `ENROL_APPLY_USER_WAIT = 2` extends a column core defines two values for — so it is a collision
  this plugin created, not a defect to report upstream; it is simply not fixable from here.

  **What the icon deliberately does not check is whether the plugin is enabled site wide.** Core's
  own Edit and Unenrol icons render on a disabled plugin's rows here (the manager resolves plugins
  through `get_enrolment_plugins(false)`) and `manage.php` has no enabled check either, so a check
  on the icon alone would hide the way in to a queue that still works.

  **The bulk menu differs, and an earlier version of this sentence named the wrong file for why.**
  It is not that core builds the menu from the enabled-only list — `user/index.php:250` passes
  `false`, meaning *include disabled*, exactly as the icon's manager does. The asymmetry is that
  `action_redir.php:189` resolves the DISPATCH through `get_enrolment_plugins()` with its default
  of enabled-only and throws `errorwithbulkoperation`. So a disabled plugin's menu entries are
  offered and then refuse to work, which is why `get_bulk_operations()` gates on
  `enrol_is_enabled()` and the icon does not: one leads to a queue that still works, the other
  leads to an exception page.

  **`enrol/locallib.php`'s `get_users_for_display()` is dead code** — measured, zero callers on
  either branch — so `user/classes/table/participants.php` is the only live caller of this method
  (`:355` on 5.2, `:347` on 5.1 — do not carry one branch's line number under a claim measured on
  both), and the participants page is the icon's only surface.

  One accessibility limitation is core's and cannot be fixed from here:
  `user/templates/status_field.mustache` hardcodes `role="button"` on every enrol action anchor.
  Core's three really are JS-driven buttons; this one is a genuine navigation link, so it is
  announced as a button and Space does not activate it.

- **Never copy either core precedent's bulk edit operation.** `enrol_manual` and `enrol_self` ship
  the same SQL character for character: a raw `$DB->execute()` UPDATE of `{user_enrolments}` with
  `\core\event\user_enrolment_updated` built by hand. Neither calls `update_user_enrol()`, so
  `\core_enrol\hook\before_user_enrolment_updated` is never dispatched — and that hook is the
  entire out-of-band approval route (see `classes/hook_callbacks.php`). A bulk approval written
  that way flips the status to active and silently skips the role, the group memberships, the
  durable record and the applicant's notification. Every operation in `classes/bulk/` delegates
  instead, and `test_a_bulk_confirmation_runs_the_plugins_own_approval` is what holds it: replacing
  the delegation with a `set_field()` reddens it on the queued task and on the component-stamped
  membership. The delete half of both precedents is safe to copy — it goes through
  `allow_unenrol_user()` and `unenrol_user()`.

- **The bulk path had to reproduce the QUEUE's predicate, and the half that is easy to miss is
  `timeend`.** The queue pairs `ue.status != :active` with `(ue.timeend = 0 OR ue.timeend
  > :now)`, and only the second clause keeps an expired enrolment out: `process_expirations()`
  re-suspends an enrolment whose period ran out, so somebody approved and enrolled long ago comes
  back looking exactly like a fresh application. That exclusion has only ever lived in the
  LISTING — `get_pending_user_enrolment()` carries no `timeend` clause at all — so the
  participants page, which is a second listing that core owns, reached rows the first listing was
  written to keep away from the decision methods. `awaiting_decision()` is where the whole
  predicate now lives.

  **Deferral used to be the worst of the three, not cancellation, which is the opposite of what it
  looks like.** `wait_enrolment()` called `update_user_enrol()` with no dates and that method
  writes a date only when one is passed, so an expired row kept its past `timeend` and became a
  deferred application carrying an expiry — a state no queue lists, and one the
  `ENROL_EXT_REMOVED_UNENROL` branch of `process_expirations()` unenrols on sight, selecting on
  `timeend` alone with no status filter. It is exactly the state "Never put a `timeend` on a
  pending application" above forbids, arrived at from the other end. **It passes `null, 0` now**
  (gate `AA`), so the expiry is cleared; the predicate still excludes expired rows, because a
  lapsed approval is not an application awaiting a decision whatever the deferral would do to it.

- **Counts are taken by re-reading, never by predicting, and there are three of them rather than
  one.** The three decision methods skip a row they will not act on and skip it silently, so the
  only truthful report is of the rows whose state actually moved. One bucket would have to carry
  three unrelated reasons under a sentence naming a single one — "not awaiting a decision" is false
  of an application refused by `can_manage_application()`, and false again of one whose enrolment
  did not move because it was already in the state being asked for. Each counter is computed from
  the set its string describes and nothing else.

  **The queue counts too, and it counts something narrower.** Each decision method RETURNS how
  many of the given applications it reached — found, authorised and written to — and `manage.php`
  reports the rest as left alone. Before that it printed "The selected enrolment applications have
  been updated" for a post that had changed nothing at all. Note that `wait_enrolment()` no longer
  skips an application already deferred: its lookup is `get_pending_user_enrolment()`, the same one
  approval and cancellation use, which is what lets a decider correct the REASON on a deferred
  application (gate `BF`).

- **Core's own bulk form cannot be reused, and the one line worth copying from it is invisible.**
  `enrol_bulk_enrolment_change_form` indexes an options array whose only keys are `-1`, `0` and `1`
  by the row's own status with no `isset()` guard (`enrol/bulkchange_forms.php:57-61` and `:84`),
  so every `ENROL_APPLY_USER_WAIT` row raises "Undefined array key 2"; and its labels come out of
  the `enrol_manual` language pack. What must still be copied is the hidden `bulkuser[]` input per
  row. The selected ids reach core from the participants table's checkbox NAMES — `user<id>`,
  scraped with `preg_match('/^user(\d+)$/')` over the whole POST — and those exist only on the
  FIRST post; on the second the ids survive purely because the form re-emits them
  (`enrol/bulkchange_forms.php:81`, read back at `user/action_redir.php:67`). A form that omits
  them submits cleanly and then redirects the operator back with "No users selected", as though
  they had ticked nothing. `test_the_confirmation_form_carries_the_selection_forward` reads them
  back through `optional_param_array()` rather than asserting on the markup alone.

- **A form-based operation that returns false fails silently.** `user/action_redir.php` has no
  `else` on the form branch: a false return falls through and redisplays the form with no message
  at all. Only the form-less branch throws. So a refusal has to push its own
  `\core\notification::error()` — which is also why all three operations return a form rather
  than acting immediately, a bulk cancellation being an unenrolment.

- **A bulk decision reaches ONE apply instance per course, and the case core does NOT warn about
  is the reachable one.** `action_redir.php` picks the FIRST `{enrol}` row of the plugin in the
  course (`enrol_get_instances($courseid, false)`, `break` on the first match — so a **disabled**
  instance sorting first captures the whole dispatch) and filters the manager to it, while the menu
  url carries only the plugin name. There is nowhere to say which instance was meant, and
  `user/index.php` asks for the operations once per instance with identical urls — so the menu
  used to carry one byte-identical optgroup per instance. **`get_bulk_operations()` now memoises
  per plugin object**, keyed on `empty($manager->get_enrolment_filter())`, so the participants
  page gets one group per course while the two FILTERED callers — the dispatch itself and
  `enrol/renderer.php` — are never suppressed. The duplication reproduces in stock core with
  `enrol_self`, so the real fix belongs in `user/index.php`; this is what can be done from here.

  An earlier version of this paragraph said "applicants of the second are dropped by core with a
  per-user warning", full stop, and that sentence argues the next reader out of the only silent
  case. It is true when the selected people are DIFFERENT: `get_users_enrolments()` returns no row
  for somebody with no enrolment on the filtered instance, and core reports the difference. For
  **one person holding an application on each of two instances** it is false — they come back
  carrying the filtered instance's row, `$removed` is empty, core warns nothing and the plugin
  reports a clean success. Measured. And that case is the reachable one, because two applications
  in one course are supported on purpose: two apply instances are two intakes.

  `queue::other_applications()` is what closes it, and it WARNS rather than deciding — twice, on
  the confirmation form before anything is written and in the report after. Deciding the others is
  the intuitive fix and is wrong on its own terms: the decision carries per-instance data (the role
  fallback, the groups, both caps), so approving one intake is a statement about that intake alone,
  a deferral note written about one is a falsehood attached to the other, and a warning is
  reversible by the operator where a decision is not.

  None of this is a reason to forbid a second instance — the plugin supports them on purpose, and
  `enrol_gapply` gets exactly that wrong. The queue reaches all of them.

- **The bulk menu needs JavaScript, which is the opposite of the queue's own bar.** Core ships the
  "With selected users..." select `disabled` in the server markup and only `core/checkbox-toggleall`
  clears it. In a non-JavaScript Behat run `I set the field ... to ...` does not throw — Mink sets
  the value regardless — but `Form::getValues()` omits a disabled field, so `formaction` is never
  posted and the step passes while nothing happens. Every core scenario driving this menu is
  `@javascript`, and so is this plugin's.

- **The report reads the LIVE enrolment as well as the record, and the two answer different
  questions.** The durable record holds the last decision this plugin's own state machine took;
  the participants page, course reset, user deletion and the expiry sweep all change an enrolment
  without touching it. `submission::decide()` is reached from exactly three call sites
  (`complete_approval()`, `wait_enrolment()`, `cancel_enrolment()`) and nothing else writes a
  status. So the entity LEFT joins `{user_enrolments}` on `userenrolmentid` and derives an
  `outcome` column from the pair.

  **The fix for "the report says Pending after an unenrolment" was read-side on purpose.** A
  write-side one would have to avoid breaking `test_a_submission_row_survives_unenrolment`, which
  pins that a record deliberately outlives its enrolment, and avoid overwriting
  `cancel_enrolment()`'s CANCELLED, which is stamped *before* the unenrol. Neither risk exists
  when nothing new is written. Full analysis in `docs/design/audit-trail-analysis.md`.

  **`userenrolmentid = 0` is not "no longer enrolled".** A restore writes 0 when it cannot map the
  enrolment, and 0 finds nothing in the join for the same reason a deleted row does. The formatter
  checks it FIRST; reporting the two alike would be a fresh falsehood of the kind the column
  exists to remove.

  **The suspended case is split on `timeend` because the halves mean opposite things.** A manual
  suspension carries no period and the queue's predicate puts that row back in the
  approval queue; an expiry carries one in the past and does not. One word for both would file
  half of them in the wrong place.

  **`outcome` is not sortable and has no filter, and that is load bearing** — it is a display
  callback, and filtering and sorting are SQL that never reach one. The sortable primitives are
  `status` and `enrolment`. The same precondition is what makes the snapshot column's masking
  sound.

  **`ENROL_APPLY_USER_WAIT` needs a `require_once` from an autoloaded class.** It is defined in
  the plugin's `lib.php`, which is not autoloaded, and the formatter is the first `classes/` file
  to need it. It is deliberately not substituted with `submission::STATUS_WAITING`, which also
  happens to be 2: one is the enrolment's status and the other the record's, equal by coincidence
  rather than contract.

- **Both listings order by a unique key as a last resort, and the injection point is the only
  one that works.** Every column either table offers can tie — `applydate` is `ue.timecreated`,
  which `enrol_user()` writes as whole Unix seconds, so a cohort admitted by one script shares a
  value, and on the live 5.2 site three pending applications already do. With no unique key the
  database may return a tied group in any order and need not repeat its choice, and each page of
  a paged table is a separately planned statement: measured on PostgreSQL 17 over a tied 100-row
  set, 11 rows appeared on two pages and 11 on none, and a unique final key gave exactly 100.

  Core's own fallback cannot cover it. `set_sorting_preferences()` appends `sort_default_column`
  when it is missing, and here that column IS `applydate` — so clicking any other heading gives
  two keys that both tie, and clicking `applydate` appends nothing.

  Override **`get_sort_columns()`**, which is what `tool_policy`, `mod_quiz` and `mod_assign` all
  do. Not `construct_order_by()`: it is static and reached through `self::`, which is early
  bound, so an override of it is never called — silently. Not `get_sql_sort()` either: appending
  to its string puts a raw fragment after core's per-driver NULL ordering. And not
  `gradereport_history`'s shape, which appends its key only when the sort is exactly the default
  one, so every other heading loses it.

  **Test the ORDER BY, never the row order.** A tie only reorders when the database chooses to,
  and at fixture size it usually does not — the five live rows page cleanly today while three of
  them share a timestamp. A row-order test passes with the tiebreaker deleted, which is how this
  survived. Note also that the emitted fragment is driver-dependent: PostgreSQL gives
  `ue.id ASC NULLS FIRST`, MariaDB gives `ue.id ASC`, so match by prefix.

- **`table_sql` writes to the output buffer.** The renderer captures it with
  `ob_start()` so it can be handed to a Mustache template as a triple stash. That is the
  one place raw HTML is passed through a template on purpose.

- **The profile fields on the application form are never saved.** The form renders the
  fields the instance asks for, but nothing calls `profile_save_data()` or writes to
  `{user}`. The submitted values only travel into the notification the approver receives.
  That is upstream behaviour and it is deliberate here: turning it into a real profile edit
  would let an enrolment form rewrite user records, which is a much larger change than it
  looks and a privacy question of its own. Do not "fix" it without deciding that
  explicitly. (An earlier version of this note also named
  `useredit_update_user_profile()`; that function exists on neither 5.1 nor 5.2, so it was
  proving nothing.)

- **Which fields are asked for is decided at two levels, and read as an intersection.** An
  administrator sets the site pool in `enrol_apply/allowedfields`; a teacher picks from it
  per instance, stored as a JSON envelope in `customtext4`. `\enrol_apply\local\fields::resolve()`
  recomputes the pool on every read and keeps only the picked keys that survive it. That is
  not defensive style: `customtext4` is carried verbatim by core's backup and copied onto a
  restored instance by `enrol_plugin::add_instance()` with no allowlist, so anyone who can
  restore a course chooses its contents. The deny list is enforced in `pool()` and nowhere
  else, deliberately — a second check inside `resolve()` is unreachable, and an unreachable
  guard no test can hold reads as protection while proving nothing.

- **The notification carries what the form delivered, and is not put through
  `format_string()`.** Both halves come from the submitted data. `format_string()` runs
  `strip_tags()`, which deletes everything from a bare `<` onwards, so a second strip is lossy
  for anything that reaches it. The notification template escapes every value through a double
  stash instead, which is lossless and correct.

  **Two things this entry used to say that are false, and both travelled into slice 7 before
  they were caught.** It is *not* true that "an applicant typing `A<B and R&D` would have the
  approver read `A`": every editable field on the form is `PARAM_TEXT` and formslib cleans the
  submission through `clean_param()` before `get_data()`, so the tail is gone at submission —
  measured, `clean_param('A<B and R&D', PARAM_TEXT)` is `'A'`. The value that really can hold
  a bare `<` comes from a **restore**, which writes `userinfodata` and `comment` verbatim out
  of a foreign archive. And a **report column does not have to satisfy `PARAM_TEXT`**: a Report
  Builder cell is raw HTML, where escaping is safe and lossless, and stripping there is a
  defect rather than a deliberate cost. Only a web service return genuinely has to strip.

- **The review page belongs to a COURSE, and `$PAGE->set_course()` is what says so.** Without it
  `$COURSE` stays the site course and the page renders the front page's own secondary navigation -
  measured, eight nodes all pointing at course id 1, including the front page's settings, on a page
  deciding another course's application. Three mechanics, and the order is load bearing: call it
  **after** `set_context()`, because it sets the page context to the course only when none is set
  and would otherwise silently replace the applicant's USER context on the mentor path; it applies
  **no access check at all**, which is exactly what makes it safe for a mentor who may decide the
  application without being able to enter the course; and it does not reliably produce a breadcrumb,
  so the crumbs are built by hand.

- **One identity rule serves the whole review page**, and the e-mail address is inside it. It used
  to have a row of its own, printed unconditionally, while the snapshot panel beside it masked
  identity fields from the same reader - one panel hiding what the other showed. Both now resolve
  through the course context, so a site that does not name `email` in `showuseridentity` does not
  see it here either. That is the owner's decision (2026-08-31) and it is a real behaviour change:
  the profile link is the route to contact details for a reader entitled to them. Gate `AR`.

  **The earlier-applications panel is gated on `enrol/apply:viewreports`, not on the capability
  that opens the page.** What somebody applied for and what was decided is the disclosure the
  report exists to control, and an editing teacher holding only `manageapplications` is not granted
  it by archetype. A reader without it gets no panel rather than an empty one - a heading that
  appears only when there IS history is itself a disclosure. Each row is worded by the REPORT's own
  outcome formatter, never the record's bare status: a record says APPROVED for ever while the
  enrolment it names may since have been suspended or removed by a route this plugin never sees.
  Gate `AQ`.

- **Cancelling asks, and button order could never have been the fix.** Confirm was the form's
  first submit and therefore its default, so Enter on either chooser approved the enrolment -
  and whichever button comes first inherits that, so no ordering makes both safe. `manage.php`
  intercepts `formaction === 'cancel'` without a `confirmed` flag and re-renders a confirmation
  that re-emits the whole POST contract, `formaction` included. **Only on the review page:** the
  queue posts the same action for a whole selection and has always applied it directly, and
  intercepting there would break the scenario that proves the queue works without JavaScript.

- **The review page's navigation wrapper is gated on `hasnav`, not on the neighbours.** A queue of
  one is exactly when a reader most needs the link back, and the old flag hid the whole landmark
  precisely then. The fourth `scope()` branch - an operator who may open no queue, which is
  reachable rather than defensive - passes null and gets no link, because one pointing at a queue
  that would refuse them is worse than none. Gate `AS`.

- **The queue's identifying columns are core's decision, and the mentee scope gets none.**
  `\enrol_apply\local\identity` is the only reader; it delegates to
  `\core_user\fields::get_identity_fields()` and `for_identity()->get_sql()`, so the queue and the
  participants page beside it cannot answer differently. Before it, the queue printed the e-mail
  address unconditionally — measured on m502, whose `showuseridentity` does not name `email` while
  its `hiddenuserfields` does list it: the participants page showed no e-mail column and the queue
  showed the address. State it that way round rather than by quoting the site's field list, which
  is dev configuration and moves.

  **Which context judges it is the whole design.** `?id=` asks about its course; the site-wide
  queue asks about the system context, which is right for an operator holding the capability there
  and fails closed for anybody else. The **mentee** queue passes null and is offered nothing: it
  spans courses in one statement, so no single context is right, and a per-row mask is unsound for
  a sortable column — a reader could recover a hidden value by sorting on it. Gate `AO`.

  **Three things not to defend against twice, all measured in core.** `get_identity_fields()`
  already drops a custom profile field that no longer exists, already drops a standard field named
  in `$CFG->hiddenuserfields` unless the reader holds the override, and already returns `[]`
  without `moodle/site:viewuseridentity`. So the INNER join `get_sql()` emits on
  `{user_info_field}` cannot empty the queue through this path — it could only do that if the field
  list came from somewhere other than that helper.

  **Two things that DO need care.** `get_sql()` must be asked for named parameters or
  `fix_sql_params()` throws `mixedtypesqlparam` — and the placeholders exist only for CUSTOM
  profile fields, so a fixture naming standard fields alone passes against the bug. And
  `flexible_table` writes `$row->$column` into the cell **with no escaping**, so `other_cols()`
  returning `s()` is this plugin's own XSS boundary, exactly as it is in core's participants table.
  Gate `AM`.

  **Both teacher archetypes hold `moodle/course:viewhiddenuserfields`**, so on a stock site
  `hiddenuserfields` never narrows what a teacher sees here; it bites a custom role holding
  `moodle/site:viewuseridentity` without that override. A test using a stock teacher to prove the
  hidden-field rule asserts the opposite of what it looks like it asserts.

- **The queue has no initials filter, and hiding the bar was never the same as removing it.**
  `get_sql_where()` reads `prefs['i_first']`/`['i_last']` and never consults `use_initials`, and
  `table_sql::query_db()` appends the result to both the count and the data query — so `out(50,
  false)` hides the control and leaves the filter running. Measured: a stored `i_first = 'Z'` took
  a three-row queue to zero with no bar on the page. The preference lives in
  `$SESSION->flextable`, not in a user preference, so it survives page loads invisibly. The
  override returning `['', []]` is the complete kill for the FILTER; emptying
  `$userfullnamecolumns` also works and silently costs the fullname column its sub-sort links.
  Gate `AN`.

  **The bar is a second override, and both are needed.** `initialbars()` is forced to false so the
  control is not drawn — with `use_initials` false, `get_initial_first()` returns null and
  `print_initials_bar()`'s condition fails on all three terms. It is an override and not a call
  because the argument is not the caller's to make: `capture_table()` passes true to `out()`, and
  core's dynamic-table service passes true unconditionally. Killing only the filter leaves an A-Z
  bar that silently does nothing when clicked, which is worse than either end state. Gate `AP`.

- **The instance's comment wording has ONE reader, `\enrol_apply\local\commentlabel`, and its
  three sinks disagree about escaping.** `customtext2` heads the applicant's comment box, the
  queue's comment column and the review page's comment label. Two of those render raw and want the
  **escaped** spelling — the queue header goes through `flexible_table::print_headers()` →
  `html_writer::tag()`, which concatenates its content without escaping it, and a moodleform
  element label is a triple stash in `element-template.mustache`. The third, `review.mustache`'s
  `{{commentlabel}}`, is a **double stash** and wants the **plain** spelling; the escaped one shows
  the reader the entities. That is why `custom()` takes an `$escape` flag rather than deciding for
  everybody, exactly as core's `field_controller::get_formatted_name()` does.

  **Only the `?id=` queue may carry it.** The site-wide and mentee scopes span instances, each free
  to word the question differently, so a single heading there would be true of some rows and false
  of others. `manage.php` passes `''` on those scopes and the table falls back.

  **The value can be a leftover recipient list.** Upstream stored the notification recipients in
  this column until 2016 and the 2022 fix retro-edited the step that wrote them, so a site past
  that savepoint kept `$@ALL@$` as its "label". `db/upgrade.php` clears the stored ones;
  `custom()` also falls back for it, because `restore_instance()` does **not** sanitise
  `customtext2` and an archive can bring one back. Only that literal is recognised — the
  comma-separated user-id list the column could also hold is indistinguishable from a real label.

  Nothing in any pipeline can see which stash a value lands in, so gates `AJ`, `AK` and `AL` are
  what hold this.

- **A name this plugin renders itself needs `'escape' => false`, and there is nothing in the
  pipeline that will tell you.** `format_string()`'s escape flag defaults to **true**, so the
  bare call returns the *escaped* spelling; every double stash in this plugin's own templates
  then escapes it a second time. Measured on 5.1 and 5.2: a group named `R&D < Team` reached
  the reader as the literal text `R&amp;D &lt; Team`. Two calls had it — the group chooser in
  `renderer.php` and the course name in the "new application" notification, whose template
  docblock had claimed since it was written that "every label and value arrives in its PLAIN
  spelling". `tests/renderer_test.php` pins both, and each of the two mutations reddens exactly
  one of its tests.

  **The opposite calls in this plugin are correct and must not be "fixed" to match.**
  `edit_form.php:76` and `:284` feed moodleform selects, the queue's comment heading feeds
  `html_writer::link()`, and every `$PAGE->set_heading(format_string(...))` feeds a triple
  stash — all four sinks render raw and want the escaped spelling. The rule is the sink, never
  the helper.

  **A role name has no single spelling, and the sentence that used to stand here said it had
  one.** It claimed `get_assignable_roles()` always returns `format_string()` output, so the
  escaped spelling was "all core will give you" and a **triple** stash was therefore correct.
  Half of that is true. `role_get_name()` runs `format_string()` only when `role.name` is
  non-empty; when it is empty the name comes from a bare `get_string()` — `defaultcoursestudent`
  and its siblings — that has never been escaped at all (`lib/accesslib.php:4575-4594`, the same
  on both branches). **Every one of the eight roles a stock site ships has an empty `role.name`**
  — measured on m502, where `manager`, `editingteacher`, `student` and the rest all return `[]`
  while the site's own custom role returns `R&amp;D coordinator`. So the list handed to the
  chooser mixes the two spellings, and no single stash is right for all of it.

  The renderer therefore normalises: it puts every name through
  `format_string($name, true, ['context' => $coursecontext])` before the template sees it. That
  is a no-op on the already-escaped half — `format_string()` is idempotent, because the ampersand
  rule skips an existing entity, measured — and escapes the other half, after which the triple
  stash is correct for every member, exactly as core's own `element-select.mustache` is. It is
  still the one place in this plugin's templates where a triple stash is right rather than wrong;
  what changed is that the renderer now earns it instead of assuming it.

- **`allow_apply()` guards BOTH doors, and until recently it guarded only the offer.** It is
  the whole eligibility predicate — instance status, `customint6`, the enrolment window, and the
  `customint5` cohort restriction with its `-1` unresolved sentinel — and its only callers were
  `enrol_page_hook()` and the form's `check_access_for_dynamic_submission()`. Both are
  presentation. `submit_application()`, which is what actually writes the enrolment, checked the
  lock, the duplicate and the `customint3` cap and nothing else, so every one of those
  restrictions was a property of two screens rather than of the plugin. Any other caller — a
  task, a web service, an import — walked past all of them.

  It is now re-checked **inside the existing lock**, first thing, next to the duplicate and cap
  checks that were always there. The comparison is `!== true` because the return is
  `bool|string` and anything that is not exactly `true` must fail closed — the cohort refusal is
  generic today, but the signature still permits detail.

  **Do not repeat the claim that this closes the form's race; it mostly does not.** Both
  transports re-run `check_access_for_dynamic_submission()` on the SUBMIT request, immediately
  before the write — `apply.php:63` calls it on every request including the POST, and core builds
  the modal's form with `$isajaxsubmission = true`, whose `dynamic_form::__construct()` runs the
  same check before `core_form\external\dynamic_form::execute()` reaches
  `process_dynamic_submission()`. So an applicant removed from the cohort while filling the form
  in is already refused there, and refused *better*: the form throws `cantenrol`, which names a
  reason. What is left for this guard is the intra-request gap between that check and the write,
  and — the real point — every caller that is not the form.

  **The second argument is load bearing and is the half a reviewer skips.** `allow_apply()` read
  `$USER` internally, which is right for the two screens (both ask "may *I* apply?") and wrong
  for a write path handed an explicit `$userid`. With the bare one-argument call, a caller
  enrolling somebody else has the cohort clause evaluated against **the operator** — so an
  admin who happens to be a cohort member admits an applicant who is not. The parameter is
  optional and defaults to the current user, because callers **outside this repository** reach
  this method through `is_callable()` and pass one argument, and all of them document it as
  resolving for `$USER`. `mutations/gates.conf` carries both halves: `Q` deletes the guard, `R`
  keeps it and drops the user id, and only the cohort test can see `R` — which is why that test
  puts the operator inside the cohort and the applicant outside it.

  **The refusal is reported through `\enrol_apply\local\application_result`, and the reason it
  has THREE states rather than a bool is the whole point.** The old `bool` fused "an application
  was already there" with "this was refused", which need opposite treatment — so
  `process_dynamic_submission()` could not route them and discarded the value, sending every
  outcome to `applied.php`. That page is itself gated on a `{user_enrolments}` row for the
  instance, so a refusal, having written none, was turned into a bare `invalidaccess`. The
  realistic way in was losing the race for the last place under `customint3`.

  Now: `created` and `already_applied` go to `applied.php` as before; `refused` pushes
  `\core\notification::error()` and returns `/enrol/index.php`. One branch serves **both**
  transports because the notification is session-carried — `apply.php` `redirect()`s to the url
  and the modal assigns it to `window.location`. Keep `already_applied` on the acknowledgement:
  the ordinary double submission reaches it, the applicant really does have an application, and
  routing it to an error would break the commonest path to buy nothing.

  **`refused()` throws on an empty reason rather than falling back to a generic string**, because
  a fallback would reinstate the unexplained refusal while hiding the caller that produced it.
  The lock branch reports `already_applied`, which is what a failed acquisition has always been
  taken to mean here — calling it a refusal would tell the applicant to try again at the moment
  it worked.

  **Testing this needs `tests/fixtures/testable_application_form.php`, and the reason is a trap.**
  `get_data()` returns **null** on a unit-built `dynamic_form`: the `_qf__` marker is absent, and
  supplying it drives the form into `require_sesskey()` and dies in `sessionlib`. Null is harmless
  for the routing, so the routing tests could use a plain form — but it silently empties the
  profile-update offer, because `diff::compute()` over no data finds no changes and
  `offer::stash()` returns before writing. So "a refused application stashes nothing" **passes
  against a build that stashes unconditionally**. The fixture overrides `get_data()` so the stash
  can actually happen, and `test_a_created_application_does_stash_a_profile_offer` is the control
  that proves it does.

  One thing that null also reveals: the *created* path hands `$data` straight to
  `submission::create()`, which is typed, so null `TypeError`s there. Production never sees it —
  `apply.php` calls this only inside `else if ($form->get_data())` and the web service only after
  `is_validated()` — but a test using a plain form on the success path will hit it.

- **One form class, two transports, and only one of them runs core's guards.**
  `\enrol_apply\form\application_form` is a `\core_form\dynamic_form`. The modal reaches it
  through core's `core_form_dynamic_form` web service; `apply.php` renders the same class on a
  page for a browser with no JavaScript. `dynamic_form::__construct()` runs
  `validate_context()` and `check_access_for_dynamic_submission()` **only when the AJAX web
  service built it**, so `apply.php` calls the access check itself and the method is widened to
  public for that reason. A guard the second transport cannot reach is not a guard.

  Two things about that page transport bite hard and silently. A `dynamic_form` adds **no
  action buttons** — the modal supplies its own Save — so rendered on a page it produces a form
  nobody can submit; `apply.php` passes `showbuttons` to ask for them. And the instance id must
  **not** be passed as the form's `$ajaxformdata` argument:
  `moodleform::_process_submission()` treats a non-empty `$ajaxformdata` as the entire
  submission, so the `_qf__` marker is never seen and the form silently never submits, with no
  error anywhere.

- **The form is built from the instance id alone.** The course is derived from it. Requiring a
  course id alongside makes every real entry point throw `invalidenrolinstance`, because the
  card's button links to `apply.php?instance=N` and nothing else — while any hand-built url
  carrying both ids works perfectly, which is exactly how it survives both unit tests and
  manual checking. `test_the_form_builds_from_the_instance_id_alone` pins it.

- **The log-in-as guard here is deliberately stricter than core's.** `enrol/index.php` refuses
  a log-in-as session only when `$USER->loginascontext->contextlevel == CONTEXT_COURSE`, so an
  administrator who used "Log in as" from a profile page walks straight past it. Submitting an
  application in somebody else's name is impersonation whichever screen it started from, so
  `check_access_for_dynamic_submission()` refuses every log-in-as session.

- **Confirmation scales with the field count.** At or below
  `application_form::CONFIRM_EACH_UP_TO` (3) editable fields, each gets its own "is up to date"
  checkbox; above that they share one. Only *editable* fields count — a locked field is
  read-only and never confirmed — and with nothing filled in there is nothing to confirm in
  either mode.

- **Nothing below the form honours a field lock.** `user_update_user()` consults no capability
  and no `field_lock_*` setting; `profile_save_data()` performs no authorisation check of any
  kind and writes whatever is on the object. Core's only defence against a forged post is
  `setConstant()` winning in `exportValues()`, and this plugin does not render a locked field
  as an input at all, so there is no constant to win. `\enrol_apply\local\profilewriter`
  therefore recomputes the writable set from the instance and the user and never trusts the
  keys it was handed — that recomputation *is* the guard, and deleting it reddens six tests.

- **An enrolment form may add to a profile but never empty it.** Core's own boundary would
  erase: `profile_field_base::edit_save_data()` ignores a value only when the property is
  ABSENT, so an empty string is written straight through. Blanking somebody's stored details
  as a side effect of applying for a course is not something an applicant asked for.

- **The completeness gate must not read through `profile_user_record()`.** Its
  `$onlyinuserobject` parameter defaults to true and
  `profile_field_textarea::is_user_object_data()` returns false, so a textarea custom field is
  simply absent from what it returns — it reads as permanently empty, and the applicant is
  told to fill in a field they already filled in, forever, with no way to satisfy the gate.
  `enrol_gapply` has exactly that defect. Read each field with `fields::current_value()`.

- **`enrol_apply_submission` is the one table nothing deletes on the way past.** Everything
  else the plugin owns is destroyed exactly when it becomes interesting: the
  `enrol_apply_applicationinfo` row goes on approval, on cancellation and in `unenrol_user()`,
  and the `user_enrolments` row goes with the enrolment. So the durable record is keyed by
  `courseid` + `userid`, with `enrolid` and `userenrolmentid` as references that are allowed to
  dangle. Two consequences a reader keeps rediscovering:

  **`delete_instance()` no longer deletes it**, which inverts what that method used to do to
  the plugin's data as a whole. It stays responsible for `enrol_apply_applicationinfo` and
  `enrol_apply_groups`. `test_delete_instance_keeps_the_submission_rows` pins it, with those
  two tables as the controls.

  **The natural key is deliberately NOT unique**, though the slice plan specified
  `UNIQUE (courseid, userid)`. Course deletion pseudonymises by zeroing `userid`, so a deleted
  course with two applicants gives two rows with the same pair — measured, not reasoned: with
  the key in place PostgreSQL says `duplicate key value violates unique constraint`. Cancelling
  and re-applying is a second, independent collision. And a third: uniqueness is enforced per
  enrolment METHOD, not per course. The lock in `submit_application()` is keyed on the instance
  id and the user, and the guard behind it is a `{user_enrolments}` lookup by `enrolid`, so a
  course carrying two apply instances — which the plugin supports on purpose — lets one user
  hold two pending rows sharing `courseid` and `userid`. Measured: both `submit_application()`
  calls return true. Note that `mdl phpunit-init` alone does **not** rebuild an existing table, so a
  mutation of `install.xml` runs against the old schema and reads as harmless — drop the test
  database first (`admin/tool/phpunit/cli/util.php --drop`) and confirm the index really
  changed before believing the result.

- **Only the two user columns carry a foreign key, and that is what core's privacy test
  reads.** `core_privacy\privacy\provider_test::test_table_coverage` decides a table holds
  personal data by looking for a column literally named `userid`, or a single-field
  `<KEY TYPE="foreign">` to `user.id`. Nothing else — indexes are never inspected. So `decidedby`
  is invisible without its key: measured, the failure message goes from
  `enrol_apply_submission (userid)` to `enrol_apply_submission (userid, decidedby)` once the key
  is added. `courseid`, `enrolid` and `userenrolmentid` get plain indexes instead, because the
  row outlives all three on purpose and a foreign key would document an integrity claim the
  design breaks by construction.

  Two traps around this. An `<INDEX>` and a `<KEY>` over the same field set is a real defect:
  `install.xml` loads it silently but `xmldb_table::addKey()` throws the moment the matching
  `db/upgrade.php` step builds the object. And **this test does not run in the plugin's CI** —
  moodle-plugin-ci resolves to `--testsuite enrol_apply_testsuite`, which is this repo's
  `tests/` directory only. Invoke it deliberately:

  ```sh
  docker exec m502-webserver-1 php /var/www/html/vendor/bin/phpunit --no-coverage \
    --filter test_table_coverage --testsuite core_privacy_testsuite
  ```

- **Pseudonymise from `\core_course\hook\before_course_deleted`, never from the
  `course_deleted` event.** `delete_course()` (`lib/moodlelib.php`) dispatches the hook, then
  empties the course, then calls `context_helper::delete_instance(CONTEXT_COURSE, ...)`, and only
  then triggers the event. Every privacy provider query is wrapped in a JOIN against `{context}`
  by `contextlist::add_from_sql()`, so a row still carrying a real `userid` after that point is
  personal data no subject access request can reach and no erasure request can delete — silently.
  Core also swallows an observer exception with nothing but a `debugging()` call.

  **Both routes leave the row looking identical**, because both complete inside
  `delete_course()`. So every straightforward test passes with the call moved to the observer.
  `test_the_pseudonymisation_runs_from_the_hook_and_not_from_the_event` is what tells them apart:
  it silences the hook with `redirectHook()` and asserts the row is then NOT pseudonymised.

- **The `course_deleted` observer sweeps orphans, not the trail.** Hook callbacks are loaded
  from every plugin directory on disk with no enabled check, so the pseudonymisation above runs
  whatever the plugin's state — which means the observer is not the safety net for that. The gap
  it does close is different: `enrol_course_delete()` (`lib/enrollib.php`) resolves its plugin
  objects from `enrol_get_plugins(true)`, so a **disabled** plugin's `delete_instance()` never
  runs, yet core deletes the `enrol` and `user_enrolments` rows anyway and leaves the two tables
  that key off them pointing at nothing. The observer deletes rows whose parent is gone, rather
  than rows of one course, because by then there is no `enrol` row left to join a courseid to.

- **The retention setting stores SECONDS despite being called `retentiondays`.**
  `admin_setting_configduration` always does, whatever unit the administrator picks in the
  dropdown. `\enrol_apply\local\submission::retention_seconds()` is its only reader for that
  reason, and `test_the_retention_setting_is_read_as_seconds` pins it.

- **The backup's `users` gate cannot be tested through a restore.** With users excluded there is
  no user mapping either, so the restore drops the record whatever the backup did — a
  restore-based test passes with the gate deleted. The gate exists to keep personal data out of
  the archive *file*, so the test reads `course/enrolments.xml` directly
  (`test_the_audit_trail_is_only_written_to_the_archive_with_users`).

  That test pins only the `users` axis, and `users` is not the whole gate — the kept-roles half
  of core's predicate is the next bullet, pinned separately by
  `test_a_kept_roles_copy_carries_only_the_kept_users_data` and
  `test_an_excluded_applicant_does_not_reach_the_copied_course`.

- **"Are users included?" is not the users setting.** Core gates its own `<user_enrolments>` on
  `empty($keptroles) && $users`, with a second branch for a course copy that keeps roles
  (`backup/moodle2/backup_stepslib.php`, identical on 5.1 and 5.2). The async copy task sets the
  `users` setting to `1` whenever roles are kept **and** user data is wanted, so reading that
  setting alone disagrees with core in both directions: with kept roles and user data it writes
  personal data for users core excluded, and with kept roles and no user data it writes nothing
  while core still writes those enrolments. Reproduce core's whole predicate; do not narrow it.

  **Nest the role check inside the users gate; do not put it beside one.** Core writes its own
  kept-role `<enrolment>` rows even with user data off, and matching that is wrong here. With
  user data off, core forces the restore's users setting off and its enrolments setting to
  `ENROL_NEVER`, so no apply instance and no user enrolment reaches the destination — core
  re-enrols the kept-role users through the manual plugin afterwards instead. Anything this
  plugin wrote in that cell would be a comment and a profile snapshot in an archive with
  nowhere to go, which is the exposure the gate exists to prevent.

  **Both halves of the role predicate matter.** `ra.roleid IN (kept)` is the obvious one;
  `ra.contextid = <course context>` is the one no fixture pins by accident, because the data
  generator assigns every role at the course context. Somebody who is a student here and a
  teacher elsewhere holds the kept role but not *here*, and core writes no enrolment for them —
  a fixture with a role held in a second course is what keeps that half honest.

  Use `EXISTS` rather than core's `INNER JOIN {role_assignments}`: a user holding two of the
  kept roles matches the join twice and the same row is written to the archive twice.

  **The leak reached the destination database, not only the archive — and believing otherwise
  argues you out of the test that catches it.** `enrol_apply_applicationinfo` is keyed on the
  `enrol_apply_userenrolment` mapping, which misses for an excluded user, so those rows really
  are dropped on restore. `enrol_apply_submission` is keyed on the USER mapping, and a
  kept-roles copy annotates every course-context role assignment into users.xml
  (`backup_roles_structure_step`, ungated by kept roles), so that mapping resolves and the row
  inserts. Drive `copy_helper::create_copy()` and its adhoc task and assert on the copied
  course's rows.

  Testing it needs its own backup helper: `backup_controller::set_kept_roles()` throws
  `cannot_set_keep_roles_wrong_mode` outside `backup::MODE_COPY`, and the repo's existing helper
  uses `MODE_SAMESITE`.

- **Core wires a plugin's restore handlers to every `<enrol>` element, not just its own.** A
  restore with `enrolments` set to "never" maps every old enrol id onto the course's **manual**
  instance, so `get_new_parentid('enrol')` returns a valid id belonging to another enrolment
  method. Measured before the guard existed: an `enrol_apply_groups` row was written against a
  manual instance, which nothing owns and nothing ever cleans up. `get_apply_instanceid()` in
  `restore_enrol_apply_plugin` is the one check both handlers go through.

- **On restore, a record whose applicant cannot be mapped is dropped, not zeroed.** An ownerless
  profile snapshot is not an audit trail, it is loose personal data. A missing *decider* is only
  zeroed, because the record is still the applicant's and still means what it says.

- **The two privacy roles are erased and exported differently, and neither is symmetric.** A
  record belongs to its APPLICANT: it carries that person's comment and profile snapshot. So a
  decider's subject access export gets the decision — status, dates, which method — and never
  the applicant's words, and an erasure request from a decider only zeroes `decidedby` rather
  than deleting the row. Deleting it would destroy a third party's data under a request that
  third party never made. An applicant's own erasure does delete the row whole.

- **The export path needs a per-record discriminator, not a per-instance one.** The state
  machine deliberately produces more than one record per course and user — cancelling and
  re-applying is the ordinary route — so a path keyed on the enrolment method silently exports
  the newest record over all the others. That is the same defect this slice fixed for
  `enrol_apply_applicationinfo`, arriving again by a different route. The row id is what keys it.

- **The retention sweep spares a record whose application is still in the queue.** Nothing ever
  expires a pending application (`apply()` enrols with `timeend = 0` precisely so
  `process_expirations()` cannot reach it), so age alone does not make one finished. Purging the
  record of an application a manager can still see would leave the decision taken tomorrow with
  no row to stamp, and it would be recorded nowhere at all.

- **The restore de-duplication cannot be keyed on `enrolid`.** `restore_instance()` ends in
  `add_instance()`, so every restore creates a brand-new enrol row and no existing record can
  carry the id the restore just made — the check would be dead code that always inserts. Key it
  on `courseid` + `userid` + `timecreated`, and note that only a restore into an EXISTING course
  can reach it at all, which is what `test_restoring_the_same_course_twice_does_not_double_the_trail`
  sets up.

- **An upgrade step's DDL guard does not make its DML idempotent.** `upgrade_plugin_savepoint()`
  runs after the whole step, so a failure part way through a backfill loop leaves the rows
  already written committed and the stored version unchanged — and re-running the upgrade, which
  is the standard recovery, re-enters the step with `table_exists()` now true. The backfill needs
  its own per-row existence check. Measured on the live m502 database: two extra runs of the
  2026082300 step change nothing.

- **A backfill's predicate must be the queue's predicate, timeend clause included.** "Not
  active" is strictly weaker: `process_expirations()` re-suspends an ACTIVE enrolment whose
  period ran out, so somebody approved long ago reads as `status != active` and would be
  backfilled as an application nobody ever decided. A false audit row is the one thing this
  table must never hold.

- **Do not add a `<> 0` filter to the privacy user lists.** `decidedby` is 0 on every undecided
  application and `userid` is 0 on every pseudonymised one, so the filter looks necessary — but
  `userlist::add_from_sql()` wraps the query in `JOIN {user} u ON u.id = target.<field>` and no
  user has id 0. The filter would be unreachable, so no test could hold it. What matters is the
  API: `add_userids()` does no such filtering.

- **`decide()`'s same-status skip is a guard, and it needed a narrow exception rather than
  deletion.** The skip stops a later no-op touch of an already-decided enrolment re-attributing
  the decision to whoever did the touching — `test_a_recorded_decision_is_not_restamped` pins
  exactly that. But it was also swallowing genuine second decisions: approve, suspend from the
  participants page, approve again, and the record never leaves `STATUS_APPROVED`, so the trail
  kept naming the first decider while `complete_approval()` queued a second notification and the
  applicant was told. The record denied a decision the plugin itself had announced.

  What separates the two cases is knowledge the CALLER has and the record does not.
  `confirm_enrolment()` only ever processes rows `get_pending_user_enrolment()` returned, which
  admits suspended and waiting-list rows only; the hook callback only fires on a status change to
  active. Both know the enrolment genuinely moved, so all three transition callers pass
  `$isfreshdecision`. A bare `decide()` call knows nothing and keeps the conservative default,
  which is why the pinned test still passes unchanged — it is the control that the exception is
  narrow.

  The double pass is safe: both run in one request with one `$USER`, so the second restamps the
  same decider milliseconds later.

- **The decision's own writers must be able to CLEAR, not only to set.** `record_decided_groups()`
  and `record_outcome_message()` both returned early on an empty value, so no path could clear a
  stored one and a re-queued application silently inherited the previous decision's groups and
  message. `record_decided_role()` never had the defect. The message writer trims rather than
  testing for blankness, which keeps the two properties apart: whitespace alone is still not a
  message, and an empty decision still clears an earlier one. `confirm_enrolment()`'s gate is
  `array_key_exists('groups', ...)` and not `!empty(...)` for the same reason — a caller with
  nothing to say about the groups omits the key, which is what the out-of-band route does.

- **A decision's own data must be written BEFORE the enrolment is mutated, and never through
  `submission::decide()`.** `complete_approval()` runs **twice** for a queue approval:
  `enrol_plugin::update_user_enrol()` dispatches `before_user_enrolment_updated` *before* it
  writes the row, so `hook_callbacks` reaches `complete_approval()` first — and that call carries
  no operator input, because the hook has none. `decide()` then skips any row already at the
  target status, so anything threaded through it on the second call is dropped in silence while
  the status still looks correct. The outcome message is recorded by its own writer before the
  status changes, and the notification reads it back off the record rather than being handed it —
  which is also what lets the approval notification work at all, since that one is sent from an
  adhoc task long after any argument would have gone out of scope. The groups and the role both
  follow that shape now, and the ROLE is the one where the naive version is worst — worse than
  the groups, which is not what the earlier note here predicted. Two group lists **union**, so a
  group the approver deselected is joined anyway; but a membership at least carries a component
  and an itemid, so it is attributable and removable. Two different roles also both get assigned,
  and a role assignment records nothing about which pass wrote it — measured, two rows, both
  looking exactly like something a human did. The dates are the exception and need no record:
  `confirm_enrolment()` writes them onto `{user_enrolments}` itself, before the hook fires, so
  core already holds the one answer both passes see.

## The phpcs trap that keeps costing a CI round

`PSR12.Classes.OpeningBraceSpace` rejects a blank line between `class X {` and the first
member. It reads as normal spacing and it has already been reintroduced three times in
this repo, each time in a file written after the previous sweep. Before pushing:

```sh
rg -U -n 'class [^\n]*\{\n\n' --glob '*.php' .
```

Expect no matches. `phpcbf` fixes it too, but the grep is faster than a CI round.

## Testing notes

- **Test metadata lives in PHP attributes, not doc-comments, and the fleet's exception does not
  apply here.** `~/dev/CLAUDE.md` keeps `@covers` docblocks alive for plugins that still support
  Moodle 4.5, because moodle-cs on the 4.05 leg cannot see attributes and reports
  `moodle.PHPUnit.TestCaseCovers.Missing` for every method in the file. This plugin declares
  `$plugin->supported = [501, 502]` and `ci.yml` has no 4.05 job, so `#[CoversClass(...)]` is
  the correct form. PHPUnit 11.5.55 raises one test-runner deprecation per file carrying
  `@covers` in a docblock; the suite is at zero and a restored docblock puts them back.
- `tests/lib_test.php` drives the state machine directly against the plugin object. Note
  that `enrol_apply` must be added to `enrol_plugins_enabled` in `setUp()`, otherwise
  `enrol_get_plugin('apply')` works but nothing core-side treats the instance as live.
- The capability gates are mutation-checked: removing the `can_manage_application()`
  call from `confirm_enrolment()` must turn `test_confirm_enrolment_requires_the_capability`
  and `test_confirm_enrolment_is_scoped_to_the_course` red, and nothing else.
- `enrol_page_hook()` is not unit tested: it needs a submitted `moodleform`. The Behat
  feature covers that path instead.
- `lib_test.php`'s `create_application()` bypasses `apply()` — it calls `enrol_user()` and
  inserts the applicationinfo row by hand — so it leaves **no** `enrol_apply_submission` row.
  Anything about the durable record must go through `apply_as_current_user()`, which drives
  the real path. A test that quietly uses the wrong helper asserts on a table that is empty.

## When in doubt

Follow the patterns in existing files. The codebase is internally consistent — if a new
file feels like it matches no existing shape, re-examine the approach.
