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
```

## Code layout

```
lib.php                      enrol_apply_plugin: the whole state machine
classes/form/application_form.php  what the applicant fills in, in a modal or on apply.php
apply.php / applied.php      the no-JavaScript transport and the acknowledgement
edit.php / edit_form.php     per-course instance configuration
manage.php / manage_table.php   the approval queue and its bulk actions
info.php / info_table.php    read-only listing of submitted comments
renderer.php                 page rendering plus the notification e-mail body
templates/                   manage, info, application_notification
classes/hook_callbacks.php   reconciles approvals made outside confirm_enrolment()
classes/local/applications.php  mentee lookup shared by the queue
classes/privacy/provider.php full provider: the plugin does store personal data
classes/task/                sync_enrolments (expiry action) + send_expiry_notifications
backup/                      group mappings only, see the gotcha below
```

## Architecture gotchas

- **`user_enrolments.status` carries a third value.** `ENROL_APPLY_USER_WAIT = 2` means
  "on the waiting list". Core only knows 0 (active) and 1 (suspended) but tests
  `status != ENROL_USER_ACTIVE` everywhere, so the extra value is inert to core and
  visible only to this plugin. Any query filtering applications must use
  `status != ENROL_USER_ACTIVE`, never `status = ENROL_USER_SUSPENDED`, or deferred
  applications vanish from the queue.

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

- **`confirm_enrolment()` is not the only way an application becomes active.** Core's
  participants page offers "Edit enrolment", which posts to `enrol/editenrolment.php`;
  that page requires only `enrol/apply:manage` and drives
  `enrol_plugin::update_user_enrol()` directly, so it knows nothing about groups or the
  application row. `classes/hook_callbacks.php` observes
  `\core_enrol\hook\before_user_enrolment_updated` and calls `complete_approval()`
  whichever route was taken. Put any new post-approval side effect in
  `complete_approval()`, never inline in `confirm_enrolment()`, or the two paths drift.
  The observer deliberately does not notify: the manager on core's screen is given no
  reason to expect a message to be sent.

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

- **The notification carries what the applicant typed, and is not put through
  `format_string()`.** Both halves come from the submitted data. `format_string()` runs
  `strip_tags()`, which deletes everything from a bare `<` onwards — so an applicant typing
  `A<B and R&D` would have the approver read `A`, silently. The notification template
  escapes every value through a double stash instead, which is lossless and correct. A
  value that later has to satisfy `PARAM_TEXT` — a web service return, a report column —
  must be stripped at *that* boundary, where losing the tail is a deliberate cost.

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

## The phpcs trap that keeps costing a CI round

`PSR12.Classes.OpeningBraceSpace` rejects a blank line between `class X {` and the first
member. It reads as normal spacing and it has already been reintroduced three times in
this repo, each time in a file written after the previous sweep. Before pushing:

```sh
rg -U -n 'class [^\n]*\{\n\n' --glob '*.php' .
```

Expect no matches. `phpcbf` fixes it too, but the grep is faster than a CI round.

## Testing notes

- `tests/lib_test.php` drives the state machine directly against the plugin object. Note
  that `enrol_apply` must be added to `enrol_plugins_enabled` in `setUp()`, otherwise
  `enrol_get_plugin('apply')` works but nothing core-side treats the instance as live.
- The capability gates are mutation-checked: removing the `can_manage_application()`
  call from `confirm_enrolment()` must turn `test_confirm_enrolment_requires_the_capability`
  and `test_confirm_enrolment_is_scoped_to_the_course` red, and nothing else.
- `enrol_page_hook()` is not unit tested: it needs a submitted `moodleform`. The Behat
  feature covers that path instead.

## When in doubt

Follow the patterns in existing files. The codebase is internally consistent — if a new
file feels like it matches no existing shape, re-examine the approach.
