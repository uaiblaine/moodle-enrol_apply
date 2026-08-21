# Design documents

Design proposals and decision records for `enrol_apply`. Nothing here ships: the
whole `docs/` tree is `export-ignore`d in `.gitattributes`, so `git archive`
leaves it out of the release zip.

| Document | Status | Covers |
|---|---|---|
| [`profile-fields-and-audit.html`](profile-fields-and-audit.html) | Approved | The design and its rationale, with UI mockups. Read this to understand *why*. |
| [`implementation-plan.md`](implementation-plan.md) | Ready, not started | Eleven slices with files, traps and a runnable verification checklist each. Read this to know *what to do*. |

Start with the decision log below, then the plan. The HTML document is the
reference you go back to when a decision looks arbitrary — it always has a reason,
and the reason is usually a defect that was found the expensive way.

`profile-fields-and-audit.html` is a self-contained page — open it in a browser.
It carries UI mockups of the enrolment card, the application form, the
post-submission page, the instance configuration and the course report. Fonts load
from Google Fonts, so the page falls back to system faces offline; nothing in the
content depends on them.

## Decision log

Decisions taken during the design and the reason each one is not the obvious
choice. These are the durable part; the document explains each in full.

### Data model

- **The audit snapshot does not live on `enrol_apply_applicationinfo`.** That row
  is deleted on approval (`lib.php:241`), on cancellation and on unenrolment, and
  `hook_callbacks.php:68` uses its existence as the proof that a status change to
  active was an approval. A snapshot stored there self-destructs at the moment it
  becomes audit-worthy. It goes on a new durable table instead.
- **The durable table is keyed by `courseid` + `userid`, not `userenrolmentid`.**
  `unenrol_user()` deletes by that key and `cancel_applications()` calls it, so a
  "cancelled" audit row would be destroyed by the cancellation it exists to
  record. `courseid` is also the only key that survives to `course_deleted`.
- **The table carries `enrolid` as well, and `delete_instance()` stops purging
  audit rows.** The only backup attachment point for an enrol plugin is the
  per-instance `<enrol>` element, which forces `enrolid`; but `delete_instance()`
  runs on every instance deletion, not just course deletion. Carrying both keys
  and changing the purge is what lets the trail both travel in a backup and
  outlive the method. This inverts current cleanup behaviour — it needs a test.
- **The per-field child table is deferred.** The JSON envelope is versioned and
  carries label, datatype, visibility, state and values per field so the child
  table can be backfilled from it later without a painful migration.

### Applicant flow

- **`enrol_page_hook()` returns a card with one button, not a form.** Two
  instances currently emit two full profile blocks with byte-identical element
  ids; moving the form off the page fixes the duplication by construction rather
  than mitigating it.
- **One `dynamic_form` class, two transports.** `single_button` degrades to a real
  form navigation, so the modal is progressive enhancement over a real page.
- **The form context is the course *category*.** `dynamic_form`'s constructor runs
  `require_login()`, which throws for a not-yet-enrolled applicant. Both core
  enrol forms do the same and say why. Every plugin authorisation decision is
  still evaluated at the course context, resolved server-side from the instance id.
- **Picked fields are classified three ways, not two.** Editable (field plus
  confirmation), locked (read-only, snapshotted, never written), absent (neither
  rendered nor snapshotted — `PROFILE_VISIBLE_NONE`, `lang`, guest, MNet,
  `!can_edit_profile()`). Hiding a locked field while still snapshotting and
  mailing it to an approver is a disclosure gap.
- **Classification happens before `addElement`.** `HTML_QuickForm::validate()`
  iterates rules by name without checking the element still exists, so
  add-then-remove or CSS hiding leaves the form permanently unsubmittable with no
  visible field.
- **`useredit_shared_definition()` is not called at all.** It adds four section
  headers unconditionally; on an all-locked SSO site that renders empty accordions.

### Profile write

- **Writing is opt-in and user-initiated**, on a plugin page after submission,
  and the plugin never redirects to `/user/edit.php` — that page cannot be
  prefilled (four request parameters, form built purely from the DB, no hook).
- **When writing is off, the applicant gets a completeness gate** that names the
  missing fields and deep-links to `/user/edit.php` with `returnto`, writing
  nothing. The safe mode is the one that needs almost no new code.
- **`customtext4` is untrusted input.** Core backs up `customtext1..4` verbatim and
  `restore_instance()` hands `$data` to `add_instance()`, which copies every key
  with no allowlist — so anyone who can restore a course chooses its contents.
  Every read intersects with the pool recomputed server-side; a deny-list alone is
  not enough. `customint8` travels the same channel and is zeroed on restore.
- **Classification is re-run at write time.** Auth locks are UI-only —
  `user_update_user()` never consults `field_lock_*` — and not rendering a locked
  field removes core's only defence (the `setConstant()` that wins in
  `exportValues()`). Only editable keys are written, never the submitted key set.
- **Whitespace-only values are trimmed and treated as empty.** `strictformsrequired`
  defaults off, so a space would otherwise satisfy a required field and be written.
- **`email`, `idnumber`, `lang` and `description` are excluded from the picker.**

### Lifecycle

- **The audit trail enters a backup under the `users` setting, not `logs`.** Both
  `logs` defaults are 0 and `users` locks `logs`, making it a strictly narrower
  gate that would restore the comments while dropping the record of the decisions
  taken on them. The trail travels only when users are included: without them,
  `process_enrol()` converts the instance to manual and `restore_instance()` is
  never called.
- **"When the recycle bin is emptied" is not a state.** The course is deleted
  outright at delete time; the bin holds only a backup zip. Cleanup happens at
  course deletion, in `delete_instance()` plus a `course_deleted` observer as the
  backstop for the case where the plugin is disabled and `enrol_course_delete()`
  skips `delete_instance()` entirely.
- **Rows are pseudonymised in `before_course_deleted`, not after.**
  `contextlist::add_from_sql()` joins against `{context}`, and the course context
  is destroyed before `course_deleted` fires — a retained row would be invisible to
  subject access and undeletable by erasure.
- **Retention is 30 days, configurable, swept on `timecreated`.** Sweeping on
  `timedecided` would retain undecided rows forever, since those carry 0.
- **An erasure request deletes the audit row.** Erasure wins over permanence; the
  trail is deliberately not tamper-evident against the data subject.
- **With `backup_auto_users` off, a bin round trip loses the trail.** Accepted and
  documented; no plugin code can change it.

### Reporting

- **A custom report cannot be course-scoped**, so the course surface is a
  `system_report` scoped by `context_course`, and the datasource is shipped
  alongside for site-level ad-hoc reporting.
- **`can_view()` carries the whole gate.** `report.php` does not run on page 2 —
  every sort, filter and page re-instantiates the report through a web service
  taking context and parameters from the client.
- **Masking is `set_is_available()` per column, filter and condition** — never a
  display callback, which filters and sorting bypass entirely.
- **Core's `enrolment:status` is not reused.** It labels
  `ENROL_APPLY_USER_WAIT = 2` as "Not current" — a legitimate but wrong label,
  worse than a visible error.
- **Download ships synchronous first**; the async ad-hoc task with progress,
  notification and a 48-hour cleanup is a later slice.

### From enrol_gapply

Four product ideas adopted, no code copied. Both plugins are GPLv3, so copying
would be permitted; nothing adopted is a substantial file, so attribution is a
CHANGELOG mention on the commits these inform.

- Enrolment role, dates and groups chosen **at decision time** rather than frozen
  on the instance — with a server-side allowlist for `roleid` and groups, which
  `enrol_gapply` lacks (its `roleid` reaches `role_assign()` unchecked, letting any
  editing teacher assign manager).
- A free-text **outcome message** the approver types, stored on the durable row.
- **Profile completeness as a pre-gate** (see above).
- An **application window** separate from the enrolment period — using core's
  existing `enrolstartdate`/`enrolenddate`, which cost no custom columns and are
  already backed up, and checked in `allow_apply()` rather than in the hook.

Rejected: the vendored DataTables/JSZip/Select2 stack, jQuery, client-side export
and column visibility, the unauthenticated `pluginfile` callback, the attachment
preview round-trip through Google and Microsoft viewers, and the `char(255)`
status vocabulary read back through a dynamic `get_string()`.

Attachments on an application were considered and dropped.

## Corrections this work found in existing docs

- `CLAUDE.md` and `classes/hook_callbacks.php:44-47` both state that the observer
  deliberately does not notify. It does: it calls `complete_approval()`, which
  queues `\enrol_apply\task\notify_approval` (`lib.php:249-252`).
- `CLAUDE.md` names `useredit_update_user_profile()`, which does not exist on
  Moodle 5.1 or 5.2.
- `renderer.php`'s `STANDARD_USER_FIELDS` lists `url`, which is not a user table
  column (it became a `social` profile field in Moodle 4.0), and `lang`, which
  core only renders for a negative user id.
- The fleet `CLAUDE.md` states that a throwing ad-hoc task is retried forever.
  `attemptsavailable` is decremented, and exhausted rows are deleted after four
  weeks.
