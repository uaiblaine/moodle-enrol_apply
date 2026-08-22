# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

- Every application now leaves a durable record — the comment, the profile details entered,
  the decision, who took it and when — in a new `enrol_apply_submission` table. It survives
  approval, deferral, cancellation and unenrolment, travels in a course backup that includes
  users, and is reachable by both subject access and erasure requests in **two** roles: the
  applicant, and whoever decided the application. The two roles are treated differently and
  deliberately so — an applicant's export carries their own comment and profile details and
  an erasure deletes their record whole, while a decider's export carries the decision without
  the applicant's words and an erasure only removes the decider's name.
- A retention period for those records, `Keep application records for`, 30 days by default,
  applied by a new daily scheduled task. Zero keeps them forever. The sweep is chunked, time
  budgeted, and skips a record it cannot delete rather than abandoning the run.
- Deleting a course now strips its records of everything personal — both user ids zeroed, the
  comment and the profile snapshot emptied — keeping only the dates and the outcome. This runs
  before the course context is destroyed, because afterwards no privacy request could reach the
  rows at all.

### Changed

- **Deleting an enrolment method no longer deletes the record of the applications made to
  it.** It still removes the pending comments and the group mappings, which are configuration.
  Removing an enrolment method is a change to the course's setup; the record of who applied and
  what was decided is not part of that setup. An erasure request, a course deletion or the
  retention period are what end it.
- The approval queue and the read-only comment listing read the comment from the durable
  record, falling back to the pending row. A participant suspended from core's participants
  page reappears in the queue, and used to do so with an empty comment because the pending row
  is deleted on approval.

### Fixed

- Restoring a course with enrolment methods excluded wrote this plugin's group mappings against
  the restored course's **manual** enrolment instance. Core wires a plugin's restore handlers to
  every enrolment method in the archive, and that restore maps every old instance id onto the
  manual one, so `get_new_parentid('enrol')` returned a valid id belonging to somebody else. The
  rows it produced were owned by nothing and cleaned up by nothing.
- The privacy export wrote every application in a course to the same path, so a course carrying
  two enrolment-upon-approval methods exported the first and then replaced it with the second.
  The subject received half their data with nothing to say so. Each application now exports to
  its own path, which also covers the case of one person applying, being cancelled and
  applying again.
- `classes/hook_callbacks.php` and `CLAUDE.md` both stated that the out-of-band approval
  observer does not notify the applicant. It does, through `complete_approval()`.

- After submitting an application, an applicant can be offered the chance to save the details
  they entered to their own profile. It is off unless a site administrator allows it **and**
  the enrolment method opts in, the applicant is always asked first, and only the fields they
  are actually allowed to edit are ever written. The offer lists exactly what would change,
  before and after, and shows no button at all when nothing would.
- When profile writing is not allowed, the same page instead names the details missing from
  the applicant's profile and links to their profile page, writing nothing.

- The application form now opens in a modal from the course enrolment page, or on a page of
  its own for a browser with no JavaScript. Both transports render the same
  `\core_form\dynamic_form`, so the two routes cannot drift apart, and an acknowledgement
  page confirms the submission instead of dropping the applicant back where they started.
- Each field the applicant is asked to check carries a confirmation. Up to three editable
  fields get one checkbox each; above that they share a single confirmation, because a
  checkbox per field turns into a wall of ticking at the size of the default field set.

- Courses choose which profile fields an application asks for, at two levels. An
  administrator sets the site-wide pool in a new `Profile fields courses may ask for`
  setting; a teacher picks from that pool for each enrolment method, and may mark any
  field required. The picked set is intersected with the pool on every read, so narrowing
  the pool narrows every existing method at once.

- `tests/local/bootstrap_compat_test.php`, which fails when the plugin reintroduces a
  Bootstrap 4 class name, ships a background utility without an explicit text colour,
  hardcodes a colour outside a `var()` fallback, declares a custom property in core's
  `--mds-` namespace, or drops a table's row-header column. None of these are visible to
  any other gate: phpcs reads PHP, the mustache lint reads structure and stylelint reads
  CSS, and none of them knows what a class name resolves to or what colour it renders.
- Applications can be restricted to the members of one cohort, through a per-instance
  `Only cohort members` setting. A course restored into another site keeps the restriction
  as a live refusal rather than silently dropping it: the cohort id is replaced by a
  sentinel that `allow_apply()` reads as "restricted, and unresolvable here".
- An application window, `Applications open` / `Applications close`, stored in the
  `enrolstartdate` and `enrolenddate` columns core already carries in its backup. It is
  separate from the enrolment duration, which decides how long an approved enrolment
  lasts. The idea comes from the `enrol_gapply` plugin, whose own window check sits in
  `enrol_page_hook()`; here it sits in `allow_apply()`, the method every caller routes
  through.
- Moodle 5.1 and 5.2 support, declared through `$plugin->supported = [501, 502]`, with
  one moodle-an-hochschulen CI job per supported branch.
- A full privacy provider (metadata, request and userlist). The plugin stores the comment
  a user submits with an application, so the previous `null_provider` declaration was
  incorrect and would have failed the core privacy compliance test.
- A `sync_enrolments` scheduled task. The `expiredaction` setting previously had no
  effect at all: it was driven by `enrol_plugin::cron()`, which core no longer calls.
- Site level settings for `maxenrolled` and `opt_commentaryzone`. Both were already read
  as instance defaults but had never been defined, so new instances silently received null.
- PHPUnit coverage of the application state machine (including the capability gates) and
  of the privacy provider, plus a Behat smoke test of apply, approve and cancel.
- `CLAUDE.md`, `CHANGELOG.md`, `.gitattributes`, `.gitignore`, `.moodle-plugin-ci.yml`
  and a pull request template.

### Changed

- The course enrolment page shows one short card per apply method instead of rendering the
  whole form inline. Two apply methods on one page previously emitted two copies of every
  profile element, so every element id was duplicated — a WCAG 1.3.1 and 4.1.1 defect that
  is now gone.
- Submitting an application is serialised per instance and user, so two tabs cannot both
  create one. The unique key behind the comment table used to turn that race into a database
  error rather than a message anybody could act on.

- The bulk action bar no longer writes Bootstrap 4 class names beside their Bootstrap 5
  spellings. `mr-2` and `custom-select` do resolve on 5.1 and 5.2, but only through
  `theme/boost/scss/moodle/bs4-compat.scss`, which wraps them in `@include
  deprecated-styles()` and which Moodle 6.0 removes; the Bootstrap 5 spelling alone is
  correct on every supported branch.
- The waiting-list row marker reads the theme's own colour token instead of a literal
  `grey`. Both supported branches ship dark mode, where a light literal paints a light bar
  inside a dark page.
- Group assignment now happens when an application is **approved**, not when it is
  submitted. Applicants no longer appear in course groups while still pending.
- Group memberships are created with `enrol_apply` as their component, so core removes
  them again with the enrolment (`unenrol_user()` in `lib/enrollib.php` only cleans up
  memberships that carry the owning component).
- The application queue and the notification e-mail are rendered from Mustache templates
  instead of concatenated HTML.
- The bulk action bar has a real submit button, so deciding on applications no longer
  requires JavaScript. The AMD module is now a jQuery-free ES module handling only the
  select-all checkbox.
- Instance updates go through `enrol_plugin::update_instance()`, which fires the
  `enrol_instance_updated` event that the previous direct `update_record()` skipped.
- `check_privileges()` is now `can_manage_application()`, returns a real boolean, and
  resolves contexts with `IGNORE_MISSING` instead of assuming they exist.
- Language packs are alphabetically ordered with the mandatory file docblock;
  `lang/en` and `lang/pt_br` are complete and in lockstep.
- The mentee lookup behind the site-wide queue now enumerates actual mentor role
  assignments (`role_assignments` at `CONTEXT_USER`, the enumeration core uses in
  `blocks/mentees/block_mentees.php`) instead of scanning every cohort peer. Besides
  removing an unbounded per-request scan, this makes the listing agree with the
  authorisation: a mentor now sees exactly the applications `can_manage_application()`
  lets them decide, including mentees who share no cohort with them.
- `$plugin->requires` moved from `2011080100` to the Moodle 5.1 release.

### Fixed

- The approver's notification now shows **what the applicant typed**. The custom profile
  fields were previously read back out of the database, so an approver reviewing an
  application saw whatever was already on the account rather than the answers in front of
  them — and because the standard fields did come from the form, the two halves of the same
  message could disagree. Values are also no longer put through `format_string()`, which
  runs `strip_tags()` and silently deleted everything after a bare `<`.
- The application queue no longer prints an arbitrary profile field value with no
  visibility check of any kind. The `profileoption` setting that drove it is removed.

- The application queue and the submitted-comments listing now name the cell that
  identifies each row, so a screen reader announces every other cell against the
  applicant's name rather than reading a row of bare values.
- **A previously approved user reappeared in the approval queue once their enrolment
  expired.** With the expiry action set to "suspend", `process_expirations()` re-suspends
  an expired active enrolment, and the queue selected purely on `status != active`. The
  queue and the comments listing now also require the enrolment period not to have run
  out; a pending or deferred application always carries `timeend = 0`, so only a
  once-approved row can be excluded by that.
- **An application approved from core's "Edit enrolment" screen left inconsistent data.**
  `enrol/editenrolment.php` requires only `enrol/apply:manage` and drives
  `update_user_enrol()` directly, so the applicant became active while still carrying an
  application row and without the groups the instance grants. A
  `\core_enrol\hook\before_user_enrolment_updated` observer now reconciles that,
  whichever route the approval took. It deliberately does not notify the applicant: the
  manager on core's screen is given no reason to expect a message.
- **A pending application could be deleted before anyone reviewed it.** Applications were
  stamped with `timeend = now + enrolperiod` at submission, and the
  `ENROL_EXT_REMOVED_UNENROL` branch of `enrol_plugin::process_expirations()` selects on
  `timeend > 0 AND timeend < now` with no status filter. With the expiry action set to
  "unenrol", any application older than the enrolment period was unenrolled rather than
  decided. The period is now stamped on approval only, where it belongs.
- Unenrolling a user through any core path (participants page, user deletion, course
  deletion) left the application comment behind, orphaned and invisible to the privacy
  provider. `unenrol_user()` now removes it first.
- The site-wide application queue was registered under `$hassiteconfig`, hiding it from
  managers who hold `enrol/apply:manageapplications` but not `moodle/site:config`.
- The comments table sorted on `applydate`, a column it never declared; `flexible_table`
  discards an unknown sort column silently, so the paged query ran with no `ORDER BY`
  at all and rows could repeat or vanish across pages.
- A cross-site course restore carried the notification recipient list over as raw user
  ids, which point at different people on the target site. It now degrades to "nobody".
- `style.css` renamed to `styles.css`, the name Moodle loads automatically, replacing the
  manual `$PAGE->requires->css()` calls.
- Typos: the string key `mailtoteacher_suject` is now `mailtoteacher_subject` (renamed in
  every language pack before the plugin reaches AMOS), and "Group assignement" is spelled
  correctly. The application confirmation string no longer embeds `<b>` and `<br/>`.
- **Cross-site request forgery on the application queue.** `manage.php` accepted
  `formaction` and `userenrolments[]` without a session key, and `optional_param()` reads
  GET as readily as POST, so a crafted link followed by a manager confirmed, deferred or
  cancelled arbitrary applications. State changes now require `require_sesskey()`.
- **Cross-site scripting in the application queue.** The course column interpolated the
  course full name straight into an HTML string. It is now escaped and linked through
  `moodle_url`.
- **Cross-site scripting in the notification e-mail.** Applicant names, profile values and
  the submitted comment were concatenated into HTML unescaped.
- SQL built by string interpolation in `manage.php` and `manage_table.php` (the mentee
  list, the user enrolment lookup and the profile field id) is now fully parameterised.
- `manage.php` read `$useradm` on two of its three branches without ever assigning it,
  and printed `$user->fisrtname`, a property that does not exist.
- `info.php` passed an undefined `$instance` to the renderer, which then dereferenced it
  for the comment column heading and fatally errored on the site-wide view.
- Notifications to course-based recipients were built and then never sent: the
  `message_send()` call was missing from that loop.
- The renderer read `icq`, `skype`, `aim`, `yahoo` and `msn` from the submitted profile.
  Those fields were removed from the Moodle user table in 4.0, so every notification
  raised five undefined-property warnings.
- `get_enroller()` used `$lasternoller` and `$lasternollerinstanceid` without declaring
  them, which PHP 8.2 reports as deprecated dynamic property creation.
- `renderer::info_form()` loaded an AMD module, `enrol_apply/info`, that has never existed.
- **Backup and restore never ran at all.** The two plugin classes sat in `backup/`, but
  core looks for them in `backup/moodle2/` (`backup_structure_step.class.php`), so they
  were never loaded — which is why nobody had noticed that the backup declared
  `enrol_apply_applicationinfo` as its source while listing the columns of the `enrol`
  table and filtering on an `enrol` column that table does not have, and the restore
  inserted `enrolid` and `courseid` into a table with neither. The classes have moved to
  `backup/moodle2/`, the code they contain has been rewritten, and `tests/backup_test.php`
  now performs a real backup and restore so this cannot rot unnoticed again.
- Backup and restore now carry the configured group mappings, with proper id remapping,
  and the comments submitted with applications when the backup includes users. A restored
  course already carried its pending and waiting-list applications regardless, because
  core restores the user_enrolments rows with their status; only the comments were lost.
- `restore_instance()` overwrote `customint1`, the "show standard profile fields" flag,
  with a remapped role id, and `backup_annotate_custom_fields()` annotated the same field
  as a role.
- `delete_instance()` now removes the plugin's own rows; deleting an instance previously
  orphaned every `enrol_apply_applicationinfo` and `enrol_apply_groups` row.
- Five `db/upgrade.php` steps ended without `upgrade_plugin_savepoint()`, which the
  savepoints CI gate rejects and which makes a failed upgrade restart from the wrong place.
- The Bootstrap 2 class `alert-error`, dead since Moodle moved to Bootstrap 4, produced
  unstyled error boxes. Messages now go through `$OUTPUT->notification()`.
- Application dates were rendered with `date()`, ignoring the user's timezone and
  language; they now use `userdate()`.
- The `newenrols` setting was labelled with the `status` strings.
- Selection checkboxes had no label, so screen readers announced nothing.
- `enrol_apply_groups` carried the placeholder table comment left by the XMLDB editor.

### Removed

- `apply_form.php`. Its work is done by `classes/form/application_form.php`, which renders
  only the fields the instance asks for rather than calling `useredit_shared_definition()`
  and `profile_definition()` wholesale.

- `show_standard_user_profile`, `show_extra_user_profile` and `profileoption`. The first
  two were all-or-nothing switches replaced by the per-field picker; existing instances are
  migrated, and an instance that collected custom fields keeps collecting them because the
  upgrade widens the site pool to match what the site was already doing.

- The `ca`, `de`, `en_us`, `es`, `fr`, `it`, `ja` and `zh_cn` language packs. They were
  between 12 and 45 keys against 101 in English, unmaintained since 2016, and translation
  for a plugin published on moodle.org belongs in AMOS rather than in the repository.
  Sites on those languages fall back to English, which is what the missing keys already
  did. `lang/en` and `lang/pt_br` remain, in lockstep.

### Security

- The profile write recomputes what it may write from the instance and the user rather than
  trusting the keys it was given. Core offers no protection here at all: `user_update_user()`
  consults no capability and no field lock, and `profile_save_data()` performs no
  authorisation check whatsoever — it writes whatever is on the object it is handed.
- A cross-site restore switches the per-instance opt-in off unconditionally, not merely when
  the restore reports itself as cross-site. `backup_is_samesite()` falls back to comparing a
  wwwroot string taken from the archive itself, so that report is forgeable, and what is being
  switched off writes to the user table.

- See the CSRF, XSS and SQL interpolation entries above. Sites running any earlier
  version of this plugin should treat the CSRF fix as the reason to upgrade.
