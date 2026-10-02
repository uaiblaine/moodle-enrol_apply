# Review context for enrol_apply

`enrol_apply` ("Enrolment upon approval") is an enrolment method that puts an approval step in
front of a course enrolment. A user applies, optionally with a comment and a snapshot of
profile fields; the enrolment is created suspended, so it grants no access; a teacher or
manager then confirms it, defers it to a waiting list, or cancels it, and the applicant is
notified. It supports Moodle 5.1 and 5.2 on one branch. It has three tables of its own:
`enrol_apply_applicationinfo` (the pending comment), `enrol_apply_submission` (a durable
record of each application, with applicant and decider) and `enrol_apply_groups`.

This repository is a fork of `emeneo/moodle-enrol_apply`. Its own `CLAUDE.md` says the fork was
byte-identical to upstream up to commit `867e248` and that everything after it is the
fork's; it does not list which features are which, so this file does not either.

## Who is trusted

- Site administrators are fully trusted, including for the notification templates and the
  retention period in the plugin settings.
- Six capabilities, all declared at course context. `enrol/apply:config`,
  `enrol/apply:manageapplications`, `enrol/apply:manage` and `enrol/apply:unenrol` default to
  `editingteacher` and `manager`; none declares a `riskbitmask`. `enrol/apply:viewreports` is
  `RISK_PERSONAL` and defaults to `manager` only; `enrol/apply:unenrolself` defaults to
  `student`.
- `enrol/apply:manageapplications` is honoured at three levels: system (every course),
  course, and the applicant's own **user context** (a mentor who holds no course access). A
  decision is authorised per application row (`can_manage_application()`), not per page.
- Every applicant is untrusted: the comment, the profile snapshot and any value a decider
  types (`outcomemessage`, `decisionnote`) are user text. Teachers who hold
  `enrol/apply:config` are trusted for the instance's description and label.

## Surfaces

- **No web service of its own**: there is no `db/services.php`. The modal application form is a
  `core_form\dynamic_form` served by core's dynamic form web service, and the queue is a
  `core_table\dynamic` table served by core's dynamic table web service; each re-derives its
  scope and checks the capability itself.
- Page scripts: `apply.php` and `applied.php` (applicant, `require_login()`), `profile.php`
  (applicant, sesskey), `unenrolself.php`, `edit.php` (`enrol/apply:config`), `report.php`
  (`enrol/apply:viewreports`), and `manage.php` (queue, one-application review, bulk
  decisions; state changes need `require_sesskey()`). Core's participants page adds three
  bulk decisions (`classes/bulk/`), gated by `get_bulk_operations()` because core's bulk
  action script checks no capability of its own.
- A report builder datasource, "Enrolment applications", for site-wide custom reports.
- Scheduled tasks `sync_enrolments`, `send_expiry_notifications`, `purge_submissions`; adhoc
  task `notify_approval`. Hooks `before_user_enrolment_updated` and `before_course_deleted`;
  one observer, `course_deleted`. Five message providers. No file serving.
- Backup and restore of group mappings, comments and the application record.
- The privacy provider is a full one (metadata, request and userlist) declaring
  `enrol_apply_applicationinfo` and `enrol_apply_submission`; `provider_test` asserts
  `component_is_compliant('enrol_apply')`.

## Facts that look like findings but are by design

- **`manage.php?userenrol=` does not call `require_login($course)`.** A mentor has no course
  access by design; access comes from `queue::require_review_access()`, which applies the same
  `can_manage_application()` every decision applies.
- **The profile snapshot is gated separately from the decision.** A reader without
  `moodle/site:viewuseridentity` in the course gets the name only: identity columns are
  absent, not blanked, so filtering and sorting cannot recover them. In site-wide custom
  reports the snapshot needs `moodle/user:viewalldetails`, and who may open such a report is
  decided by Moodle's report capabilities and audience, not by this plugin.
- **Writing answers back to a profile (`profilewriter`) is opt-in twice**: a site setting that
  defaults off and a per-instance switch that every restore zeroes. Core's profile functions
  authorise nothing, so the writer recomputes which fields the user may edit from the
  instance, never from the posted keys, and never blanks a stored value.
- **The application form refuses every "log in as" session**, stricter than core's enrolment
  page, because applying in someone else's name is impersonation.
- **An approval made through core's "Edit enrolment" is reconciled afterwards** by the
  `before_user_enrolment_updated` hook, because `enrol/apply:manage` legitimately edits
  enrolments; forbidding that path would also remove date editing.
- **The application record outlives its enrolment on purpose.** Deleting the course
  pseudonymises it before the course context disappears (a hook, because the event fires too
  late); an erasure request deletes the records the person submitted, and for records they only
  decided removes their name and keeps the rest. The retention task spares records still awaiting a
  decision.
- Decider text is stored as `PARAM_TEXT` and escaped again at the sink (`s()` and `nl2br()`
  in the e-mail, `format_text(..., FORMAT_PLAIN)` on pages); the notification templates are the
  administrator's trusted HTML. The instance description is rendered with `format_text()`.
- `$plugin->supported = [501, 502]`: code that branches on the core version or calls a helper
  only when it exists is deliberate.

## De-emphasise

- `amd/build/**` is minified output of `amd/src/**`; review the source.
- `lang/**`, `docs/**`, `mutations/**` and `tests/**` carry no production behaviour.
- Layout and wording of the Mustache templates, unless they show a value to a reader who
  may not see it.
