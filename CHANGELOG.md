# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

- **Applications can now be decided from the course participants page.** Confirming, deferring
  and cancelling used to be possible only on the plugin's own queue, so a teacher already
  looking at the participants list had to leave it and find the applicants again. The three
  decisions now appear in the participants page's own *With selected users...* menu, under
  *Course enrol confirmation*, and ask for confirmation before acting.

  A bulk decision takes exactly the same route as the queue's. The applicant is notified, the
  chosen groups are joined, the chosen role is assigned and the applications trail is stamped,
  all once and not twice — a bulk approval and a queue approval leave the same state behind.
  Confirming also offers the group and role choosers; deferring and cancelling take only the
  message. Selected users who are not waiting for a decision are left alone and counted, so the
  result says how many applications were decided, how many of the people selected had no
  application awaiting one, and how many applications it did not change.

  "Waiting for a decision" means the same thing on both screens, expiry included. An enrolment
  that was approved and has since lapsed reads as suspended to Moodle, so it sits on the
  participants page looking like an applicant; the plugin's own queue has always excluded it, and
  a bulk decision excludes it too rather than cancelling somebody's finished enrolment or
  re-approving it in the name of whoever happened to tick the box.

  Two limits worth knowing, both of them Moodle's rather than this plugin's. The menu needs
  JavaScript, because the control that opens it is disabled until a row is ticked. And where a
  course offers more than one enrolment-upon-approval method, the bulk decision reaches the
  applications of the first one only; Moodle offers the menu no way to say which method was
  meant. The plugin's own queue reaches all of them.

### Fixed

- **Approving an application a second time is now recorded as a second decision.** If an approved
  participant was suspended from the participants page and then approved again, the applications
  trail went on naming the first person to approve them and the date they did it — while the
  applicant was told about the second approval, correctly. The record contradicted a message the
  plugin itself had sent. It now names whoever decided last.

  The protection that caused this is still in place and is still right: merely touching an
  already-decided enrolment does not re-attribute the decision to whoever touched it. Only a
  genuine change of enrolment counts as a new decision.

- **A decision no longer inherits the groups and the message of the one before it.** Approving a
  re-queued application with the group chooser and the message box left alone silently re-used
  whatever had been chosen and typed for the earlier decision — re-joining groups nobody had
  picked this time, and re-sending a message nobody had written for it. Leaving either control
  alone now means what it looks like it means. Whitespace on its own is still not a message.

- **A renamed role no longer reaches the approval queue's role chooser unescaped.** Moodle spells
  a role's name two different ways — a role that has been given a name of its own comes back
  escaped, while the eight roles a site ships with come back as plain language strings that have
  never been escaped — and the chooser was written as though only the first kind existed. A site
  that had put an `&` into one of those language strings would have seen it rendered as markup.

- **An approved applicant no longer loses their role when the course is restored or copied.**
  The role assignment is tagged with this plugin as its component, and Moodle hands any such
  assignment back to the plugin that owns it on restore — down a path that has no fallback and
  logs no warning. This plugin was not listening, so a restored applicant came back with a live
  enrolment and no role at all: still in the course, silently stripped of everything the role
  allowed, and showing "No roles" on the participants page. Measured on 5.1 and 5.2 with three
  controls in the same restore, all of which survived. Assignments made before the tagging was
  introduced were never affected.

- **A group name or a course name carrying an `&` or a `<` is no longer escaped twice.** The
  group chooser on the applications queue and the course name in the "new application"
  notification both went through `format_string()`, whose escape flag defaults to on, and were
  then output through a Mustache double stash, which escapes again. A group named `R&D < Team`
  reached the reader as the literal text `R&amp;D &lt; Team`. Both now hand the plain spelling
  to the template, which is the only one of the two spellings a double stash wants.

- **An enrolment method that asks for nothing no longer opens an empty window.** Where the method
  requests no profile fields, no comment and carries no introduction, the application form had
  nothing in it at all — the applicant saw a blank window with a Save button and no indication of
  what saving would do. It now says what submitting will do, and names the course.

- **A restored application record holding a structured value no longer breaks the page that
  reads it.** The frozen snapshot is JSON that another site wrote and a restore copies in
  verbatim, so a field value could be an array or a nested object rather than a string. Casting
  one to text emitted a PHP warning and rendered the literal word `Array` as though the
  applicant had typed it. Such a field is now skipped, and the entry is dropped whole rather
  than repaired — an entry whose key is unusable cannot be matched against the list of fields a
  reader is allowed to see, so it must not be rendered at all.

- **A course copy that keeps roles no longer copies the application data of users it was
  meant to leave out.** Core backs up only the enrolments of users holding a kept role, but the
  copy sets the site's `users` backup setting to 1 whenever roles are kept and user data is
  wanted — so this plugin, which read that setting alone, carried every applicant's comment and
  profile snapshot, including those of the people the copy exists to exclude.

  The excluded applicants' durable records did not merely sit in the archive: they were
  **inserted into the copied course's database**, under live user ids, for people with no
  enrolment in that course at all. Their pending comments were dropped, because those are keyed
  on the user-enrolment mapping, but the durable records are keyed on the user mapping — and a
  kept-roles copy puts every course-context role holder into the backup's user list, so that
  mapping resolves.

### Added

- **The applications report now says what actually happened to each application.** Two new
  columns: *Enrolment now*, which is the live state of the enrolment the application created, and
  *Outcome*, which combines that with the recorded decision. Before this, an applicant who was
  approved and one who was approved and later unenrolled were the same row on screen, and an
  application whose enrolment was removed while still pending read "Pending" for ever even though
  nobody could ever decide it.

  The eight outcomes it distinguishes: awaiting a decision; never decided and no longer enrolled;
  approved and enrolled; approved then suspended (which is what puts it back in the approval
  queue); approved then expired; approved then unenrolled; on the waiting list; cancelled.

  Nothing about the recorded decision changed — this reads data the plugin already stored. A
  record restored from a backup that could not identify the enrolment reads "Unknown" rather than
  claiming the person was removed. *Outcome* is deliberately not sortable and has no filter, because
  it is worked out per row rather than in the database; sort or filter on *Status* or *Enrolment
  now* instead.

### Changed

- **The applications queue now uses Moodle's own bulk-selection machinery.** The row checkboxes,
  the "Select all" header checkbox and the bulk action are one `core/checkbox-toggleall` group
  instead of markup this plugin invented, and the action itself has moved into a core sticky
  footer, so it stays reachable at the bottom of the window however long the queue is. Nothing
  about the queue's behaviour changes: the same checkboxes, the same labels, the same actions.

  **The action is now greyed out until you select somebody.** Core's module only re-enables such
  a control — it never sets the initial state, which is why every core page hardcodes the
  disabled attribute in its markup. This plugin does not, deliberately: its queue works without
  JavaScript, and an attribute that only JavaScript can clear would take that away to buy an
  affordance only JavaScript users see. It is switched off from the plugin's own module instead,
  so with JavaScript you get the affordance and without it you get the working queue. Moodle
  itself parks a sticky footer just off the bottom of the window and slides it up with
  JavaScript, so the plugin's stylesheet brings it into view for a browser that has none —
  without that, moving the bar there would have hidden the only button on the page from exactly
  the people the enabled control was left enabled for.

  One small loss: the "Select all" checkbox no longer shows a partial state when some but not all
  rows are ticked. Core's own machinery has no such state, and keeping the plugin's version of it
  would mean keeping code no test in this repository can execute.

### Added

- **A decider can choose which role an approved applicant is given**, on the enrolment
  applications queue, instead of the role being fixed on the enrolment method. Choosing nothing
  keeps the method's own role, so nothing changes for a site that does not use it. Only roles you
  may assign in that course are offered, and a role posted by any other means is refused and the
  method's own role used instead.

  **The role is now assigned when an application is approved, not when it is submitted.** That is
  what the setting has always said — "Role assigned to a user when their enrolment application is
  approved" — and it is a real behaviour change, not only a tidier one. Until now a pending
  applicant held the role while they waited, which meant they satisfied `has_capability()` in the
  course and were returned by `get_users_by_capability()`: anything asking those questions without
  also checking the enrolment treated somebody who had merely applied as a participant. A pending
  applicant now holds no role at all, and their Roles cell on the participants page is empty until
  they are approved.

  **One transitional wart, stated rather than hidden.** An application already in the queue when
  you upgrade keeps the role it was given on submission. There is deliberately no upgrade step to
  strip it: those assignments carry no marker saying this plugin made them, so removing them in
  bulk would also remove the same role from anyone who holds it from another source. Approving
  such an application with a *different* role therefore leaves the person holding both, and the
  older one has to be removed by hand from the participants page. Approving it without choosing a
  role — which is the default — behaves exactly as before.

  The new assignment is tagged with this plugin as its component, which the old one was not, so
  core removes it again when the enrolment ends or expires. Roles remain unprotected, so a teacher
  can still change one by hand on the participants page.

- **A decider can choose which groups an approved applicant joins**, on the enrolment
  applications queue, instead of the groups being fixed on the enrolment method. Choosing
  nothing keeps the method's own list, so nothing changes for a site that does not use it. A
  group belonging to another course is refused, and the choice is kept on the application's
  durable record.
- **A decider can write a message to the applicant**, on the enrolment applications queue, and it
  travels with whichever decision they take — approval, deferral or cancellation. It appears in
  the notification the applicant receives, below the standard wording, and is kept on the
  application's durable record.
  Leave the box empty and nothing changes.
- **A site-wide report source for enrolment applications**, so an administrator can build custom
  reports across every course from Site administration → Reports → Report builder. It reuses the
  same data as the course report and adds a course filter and a course name column, which the
  course report deliberately does not offer — a course-scoped report carrying a course filter
  would let a manager page sideways into a course they were never given.

  **The profile details an applicant submitted are withheld here unless the reader holds
  `moodle/user:viewalldetails`**, and are withheld by removing the column rather than blanking
  it, so it cannot be added, filtered or sorted either. The course report can ask that question
  of the course; a custom report has no course and no per-request permission check of this
  plugin's at all — access to it is governed by Moodle's own report capabilities and by the
  report's audience. Be aware of what that means in practice: a report can be shared with an
  audience wider than its creator, and a **scheduled** report is rendered once with the
  creator's permissions and mailed to every recipient, so the file they receive carries the
  creator's answer rather than their own.

  This surface is switched off with the site's `Enable custom reports` setting. The course
  report is not — it keeps working with custom reports disabled, which is why both exist and
  why neither is redundant.
- **A report of a course's enrolment applications**, at Course → Participants → Enrolment
  methods (the report icon) and on the course settings navigation. Built on Moodle's Report
  Builder, so it brings per-user filters, sorting, paging and a CSV or Excel download: who
  applied, when, their comment, the profile details they submitted, the outcome, who decided
  it and when. It covers every application the course has recorded, not just the queue, and
  it is scoped to the course rather than to one enrolment method — where a course has more
  than one Course enrol confirmation method, the report offers a filter to narrow by it.
- A capability of its own for that report, `enrol/apply:viewreports`, granted to managers and
  **not** to editing teachers. The capabilities around it — configuring a method, deciding an
  application, managing enrolments — all default to editing teacher, which is right for those
  and wrong here:
  the report carries the profile details of every applicant the course has ever had, long
  after their enrolment has gone. It is declared `RISK_PERSONAL`. A reader who does not hold
  `moodle/site:viewuseridentity` in the course sees the applicant's name and nothing more —
  the identity fields are absent rather than blank, because a filter or a sort on a blanked
  column would give the value back.
- Every application now leaves a durable record — the comment, the profile details entered,
  the decision, who took it and when — in a new `enrol_apply_submission` table. It survives
  approval, deferral, cancellation and unenrolment, travels in a course backup that includes
  users, and is reachable by both subject access and erasure requests in **two** roles: the
  applicant, and whoever decided the application. The two roles are treated differently and
  deliberately so — an applicant's export carries their own comment and profile details and
  an erasure deletes their record whole, while a decider's export carries the decision without
  the applicant's words and an erasure only removes the decider's name.
- A retention period for those records, `Keep application records for`, 30 days by default,
  applied by a new daily scheduled task. Zero keeps them forever. The sweep takes decided and
  abandoned records alike, but spares one whose application is still awaiting a decision —
  nothing expires a pending application, so age alone does not make it finished. It is chunked,
  time budgeted, and skips a record it cannot delete rather than abandoning the run.
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

- An approved applicant's group membership is no longer silently lost when the course is
  restored. Memberships are stamped with this plugin as their component so that core's
  `unenrol_user()` can clean them up, and core routes any component starting with `enrol_` to
  `enrol_plugin::restore_group_member()` — whose base implementation is empty. Unlike the
  generic branch beside it, that path has no fallback and logs no warning, so the membership
  simply disappeared. The base implementation is empty on purpose, because the plugins core
  had in mind re-derive their memberships from a cohort or a linked course; this one cannot,
  because the membership follows a one-off approval decision that nothing re-runs.

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
