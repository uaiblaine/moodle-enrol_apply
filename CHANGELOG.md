# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Removed

- **The *Enrol info* page is gone.** `enrol/apply/info.php` listed the comments submitted with
  the applications awaiting a decision. Measured against the approval queue, it was a strict
  subset of it in every dimension that matters: the same rows, built from the same predicate
  helper; the same capability, so nobody could reach one and not the other; and three columns the
  queue already had. Its only inbound link was one action icon on the enrolment methods page, and
  no test asserted that icon existed. The one thing it rendered that nothing else did — the
  instance's own wording above the comment column — now appears on the approval queue and the
  review page instead, which is where the comments are actually read.

  The `submitted_info` string is retired through `lang/en/deprecated.txt` and, as core's
  deprecation contract requires, its definition stays in both packs.

### Fixed

- **Two strings were listed as deprecated while their definitions had been deleted**, which fails
  core's own `core\string_manager_standard_test::test_validate_deprecated_strings_files` on both
  supported branches. Moodle's deprecation contract keeps the definition and warns at
  `debugdeveloper` when it is used; deleting it means every deprecated string in the file fails
  the assertion. `maxenrolled` and `maxenrolled_help` are restored. Nothing in this plugin's own
  CI could see it: `moodle-plugin-ci` runs the plugin's testsuite, and that test is core's.

- **The *Custom label* setting could be showing a leftover notification recipient list.** Upstream
  made `customtext2` the notification recipient list in June 2016 while still reading the custom
  label from the same column, and the February 2022 fix that moved the list to `customtext3`
  retro-edited the upgrade step that had written it — so a site already past that savepoint never
  re-ran the step and kept the value. On such a site the applicant's comment box is headed
  `$@ALL@$`. An upgrade step now clears exactly that literal, and the reader falls back to the
  shipped wording for it as well, because a restore can bring one back: `customtext2` is the one
  custom field `restore_instance()` does not sanitise.

  Only that literal is cleared. The same column could also hold a comma-separated list of user
  ids, and that shape is deliberately left alone — it cannot be told apart from a label somebody
  genuinely typed. The cleanup is one-way; the recipient list itself has lived in `customtext3`
  since 2022, so nothing that is still read is lost.

- **The *Custom label* now does something visible, and says when it does not.** Its wording heads
  the comment column on the approval queue and the label on the review page, so the question a
  teacher asks and the answers they read carry the same words. The field is hidden unless
  *Commentary field* is on — it labels a box that otherwise does not exist — and it has a help
  text saying so. Its dead default is gone: it never once pre-filled, and reviving it would have
  frozen the creating teacher's own language into the database.

  Each of the three places the wording is read needs a different escaping, and nothing in any
  pipeline can see which: two render raw markup and one renders through a Mustache double stash.
  One helper now owns the switch, and three mutation gates hold it.

- **Two English strings said the wrong thing.** *Enrol date* is the date the **application** was
  submitted, which the Brazilian pack has always said correctly; it is now *Application date*. And
  the queue's help text promised grey rows while the stylesheet has drawn a grey left bar since
  the queue was restyled.

### Changed

- **The cohort refusal no longer names the cohort.** When a non-member is refused by the
  `customint5` restriction, `allow_apply()` used to return "Only members of cohort 'X' can apply
  for enrolment", with X interpolated. That string is not confined to the page: `enrol_page_hook()`
  renders it to any authenticated non-member, and it also travels through `get_enrol_info()` into
  the `status` field of `core_enrol_get_course_enrolment_methods`. On a platform whose cohorts are
  named after the corporation they belong to, the cohort name is itself the sensitive fact — it
  tells a stranger which force a course belongs to. The refusal now says only that enrolment is
  restricted to a specific cohort, which is the part an applicant can act on. Note that core's
  `enrol_self` still names the cohort in the same situation; closing that would mean forking a core
  plugin, and has deliberately not been done.

### Added

- **Places: a second, separate number.** The enrolment method now carries two limits that answer
  two different questions. *Maximum applicants* is how many applications it will accept — when
  that is reached, nobody else may apply. *Places* is how many applicants may be **approved** at
  one time.

  The gap between them is the point. Approval here is discretionary, so not every applicant is
  approved, and a method can sensibly accept thirty applications for ten places. Until now there
  was one number trying to be both.

  **Reaching the places number does not block an approval.** The manager is told and decides —
  which is the premise the whole plugin is built on. The warning appears on the approval queue,
  including when that queue is *empty*, which is exactly when somebody most needs to know why
  nothing is arriving.

  Both numbers ignore enrolments whose period has ended, for the same reason the applicant limit
  already did. Places default to 0, meaning no limit, on every existing enrolment method: the
  feature is opt-in and nothing changes until somebody sets a number.

  Applicants are not shown either number. They are told that applications are open or closed,
  and nothing more. Showing how many places remain would be honest about the places and
  misleading about the odds, because what actually decides an applicant's chances is how many
  other applications are pending — the number the site is least willing to publish.

### Changed

- **"Max enrolled users" is now "Maximum applicants", which is what it always counted.** The
  label said *enrolled*, the help text said *apply*, and the code counted applications — the
  label and the help had contradicted each other since the feature was written, and the help was
  the one telling the truth. The setting also moves out of the *Profile fields requested* section
  of the instance form, where it had been sitting by accident: nothing closed that section, so
  everything after it inherited the wrong heading — and when a method requested no profile
  fields the heading was never opened and the same setting appeared somewhere else entirely.

  Sites that translated the old label will see the shipped English until they translate the new
  one. That is deliberate: the string's *meaning* changed, so a site that customised the old,
  wrong label would otherwise keep it — now beside a second number it no longer distinguishes
  itself from.

### Fixed

- **A course whose places expired stopped accepting applications for ever.** The limit on how
  many people may apply counted every enrolment the method had ever made, including those whose
  enrolment period had already run out. Nothing frees those: the plugin ships *Action on
  enrolment expiry* set to *Keep*, under which Moodle deliberately changes nothing when a period
  ends, so the row stays and goes on occupying a place indefinitely.

  A course with a limit of 100 could therefore admit 100 people in its first year, watch all of
  them expire, and in its second year refuse everybody — with an **empty** approval queue, and
  no screen anywhere able to say why. The limit now counts only enrolments that still hold a
  place. An enrolment that has not started yet does still hold one, because that person is going
  to turn up.

  The number this plugin shows will now differ from the *Users* column on Moodle's own
  *Enrolment methods* page, which counts every row regardless. That divergence is deliberate and
  is the lesser of two evils.

- **Deferring an application to the waiting list clears any expiry it was carrying.** An
  application that had been approved and then deferred kept its old end date, and that state was
  a dead end: Moodle's expiry sweep skips it because the enrolment is not active, and the
  approval queue hides it because it has expired. It waited for a decision that no screen could
  offer and no sweep could take. Sites carrying such rows already have them repaired on upgrade.

- **An application that is refused now says so, instead of showing an access error.** The
  method that writes an application could refuse for four reasons, and reported all four as a
  bare `false` that the form discarded — every outcome was sent to the acknowledgement page.
  For a refusal that page found no enrolment row of its own to show, so it refused in turn,
  and the applicant read *"Invalid access detected"*. The realistic way to reach it was losing
  a race for the last place: two people apply, one gets it, the other is told nothing useful.

  The refusal now carries its reason. The applicant goes back to the course enrolment page,
  where the other enrolment methods are, and is told why — the course is full, applications are
  closed, the enrolment window has passed, or enrolment is restricted. One code path serves both
  the pop-up form and the plain page.

  Note the outcome that is deliberately *not* a refusal: submitting twice, whether by a double
  click or two tabs. There is an application, so the acknowledgement page is telling the truth,
  and it still appears. That distinction is the reason the write door now reports three outcomes
  rather than two — the previous `false` fused "already there" with "refused", which is why
  nothing could route them differently.

- **"The maximum number of users allowed (30) has already been reached" no longer states a
  number that is not the maximum.** The message was handed the current count rather than the
  configured limit, so the two differed whenever they could differ at all. It also published a
  ceiling that an applicant cannot act on and that says how contested a course is. The message
  now says only that no more applications are being accepted.

- **A refused application no longer leaves a profile-update offer behind.** The offer to save
  what was just typed was stashed in the session whatever the write door did, so a refusal left
  an offer attached to an application that does not exist.

- **The rules deciding who may apply are now enforced where the application is written, not
  only where it is offered.** `allow_apply()` — which settles whether the enrolment method is
  accepting applications at all, whether the enrolment window is open, and whether the
  applicant belongs to the cohort the method is restricted to — was consulted by the course
  enrolment page and by the application form, and by nothing else. The method that actually
  creates the application never asked it anything. Any other route into that method wrote the
  enrolment regardless, so the restrictions were a property of those two screens rather than
  of the plugin, and anything added later — a scheduled task, a web service, an import — would
  have walked past all of them without a word.

  The check runs inside the lock the write already takes, alongside the duplicate and
  places-cap checks that were always there, so the decision and the write cannot be separated.

  It is worth being exact about how much of a race this closes, because it is less than it
  first appears. Both ways of reaching the form re-run its access check on the *submit*
  request itself, immediately before the write, so an applicant who is removed from the cohort
  while filling the form in was already refused — and refused better, with a message naming
  the reason, rather than by the write door. What is genuinely new is every route that is not
  the form at all, which is the case this entry is about.

  Nothing changes on the ordinary path: an applicant who is not eligible is still refused by
  the form first, with the same message, on the same screen.

- **`allow_apply()` now accepts the applicant it is judging.** It read the current user from
  the session, which is correct for the two screens that call it — both ask "may *I* apply?" —
  and wrong for the write path, which is handed a user id and may one day be reached for
  somebody other than the operator. Passing no second argument keeps the previous behaviour
  exactly, so callers outside this plugin are unaffected; the cohort clause is the only rule
  that reads a person rather than the enrolment method.

### Added

- **An application can now be opened for a decision straight from the course participants
  page.** A row awaiting one carries a new icon in the enrolment status column, which opens that
  application's own review page — who applied, what they wrote, what they submitted, and the three
  decisions. Reaching it used to mean leaving the participants list, opening the plugin's queue and
  finding the same person again. It sits alongside *Edit enrolment* and *Unenrol* for anyone who
  has those, which are separate permissions from the one this icon needs.

  The icon appears only where there is something to decide: an approved enrolment does not carry
  it, and neither does one that was approved and has since lapsed. That second case is worth
  knowing on a site whose *Action on enrolment expiry* is set to suspend, because Moodle then
  shows the lapsed enrolment as *Suspended* on this very page, which is also how a fresh
  application looks. On the default setting, which changes nothing when an enrolment expires,
  Moodle shows it as *Not current* and it was never mistakable for an application.

  The icon is also the only thing on a deferred applicant's row that says a decision is still
  owed. Moodle paints a waiting-list enrolment with a green *Active* badge, and that is not a bug
  to report: the waiting-list state is a third value this plugin stores in a field Moodle defines
  two values for, so Moodle renders what it knows. It cannot be corrected from this plugin.

  It is offered to whoever may decide applications in the course, which is the same reading the
  bulk menu on this page takes. A mentor's permission is over a person rather than over a
  course, so it is the plugin's own queue that serves them, exactly as before. Taking a decision
  returns you to the list of applications you can open, not to the participants page.

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

- **One application can now be reviewed on a page of its own, by anybody who may decide it.**
  The single-application link showed the whole approval queue narrowed to one row — bulk
  checkboxes, select-all and all — and offered neither the group chooser nor the role chooser,
  so the one screen dedicated to a single decision was the one screen that could not take it.
  It is now a page: who applied, for which course, when, what they wrote, and the same three
  controls the queue offers, with a button per decision.

  It also stopped being a page only a mentor could open. It required the deciding permission in
  the applicant's own profile, which a course teacher does not hold there, so a teacher
  following the link got an error where a mentor got the page. The permission it asks for is
  now the one every decision asks for, which means a site administrator, a teacher of the
  course, and a mentor of the applicant all reach it — and nobody else.

  A mentor sees the application and can decide it, but is not offered the group and role
  choosers: those list what is in the course, and a mentor's permission is over a person, not
  over a course. The enrolment method's own groups and role still apply to whatever they
  decide.

  Two rough edges went with it. Deciding from that page used to send you back to it, where the
  application you had just decided was no longer listed; it now returns you to whichever list
  of applications you can actually open. And a link to an application whose enrolment had since
  been removed used to produce a database error page, while one that had merely been decided
  produced an empty list with no explanation; both now say what happened and offer the way
  back.

- **The review page now shows the details the applicant submitted with their application.**
  Reviewing an application meant reading the comment and nothing else — whatever profile details
  the enrolment method asked for went into the notification e-mail and were then unreachable from
  the page where the decision is actually taken.

  What is shown is the record of what the applicant typed, frozen at the moment they submitted
  it, with the labels they saw at the time. Where their profile has since moved on, the row says
  what it holds today as well; where it has not, it says nothing, so the note means something
  when it appears.

  It follows the same rule the applications report already applies to the same stored record: a
  reader without permission to see user identity fields in that course gets only the name fields
  **of this panel**, and the withheld rows are withheld whether or not the applicant filled them
  in — a marker that showed up only where there was data would answer the question it was
  hiding. The rest of the page is unchanged, so the applicant's e-mail address still appears in
  its own row as it always has; the masking governs the submitted details, not the page. One
  consequence is worth knowing: a **mentor** holds nothing in the course, so the panel gives them
  the name fields only, even where their own mentor role would let them see more elsewhere.

  The panel does not read the applicant's live profile at all. An earlier draft showed what each
  field holds today beside what was submitted, and that turned out to be a way to read arbitrary
  columns of the user record — the field names come from the stored application, which a course
  restore can carry in from another site.

- **The review page now walks the queue.** Reviewing one application was a dead end: the only
  route to the next one was back to the list and down it again. Previous and next links now sit
  above the decision, and each one names the applicant it leads to rather than pointing an arrow
  at them.

  Which queue is walked follows whoever is looking, and it is worked out from what they may
  open rather than from anything in the link. Someone who can open a course's approval queue —
  its teachers, and an administrator looking at that course — walks that course's applications.
  Someone granted the permission across the whole site, but who cannot get into the course
  itself, walks **every** application on the site, in every course. A mentor walks the
  applications of the people they mentor, across every course those people applied to. Those are
  the same three levels that decide who may take a decision at all, so every application the
  links can reach is one the reader may decide — and if none of the three fits the application
  being looked at, there are no links rather than the wrong ones.

  The walk follows the queue's own order — oldest application first, and a stable order within a
  group submitted in the same second — and not any order the queue has been re-sorted into. Nor
  does it follow the alphabetical filter above the list. So the walk can disagree with the list
  on screen, which is exactly why each link names its destination: whoever is reading can see
  where "next" goes before they follow it.

  Where a decision sends the operator is now the same question, answered once, so the two can no
  longer disagree about which list is theirs. The links are also always links within the list the
  application itself belongs to: a mentor looking at an application none of the people they mentor
  made is offered no navigation, rather than a "next" in somebody else's course with no way back.

### Changed

- **For site-local customisations only:** `enrol_apply_manage_table`'s constructor lost its
  second parameter, which restricted the queue to a single application and could no longer be
  reached — the single-application view became a page of its own. The signature is now
  `__construct($enrolid, $mentees)`. A surviving call of the old three-argument shape does not
  error: PHP discards the extra argument and the old third value lands in `$mentees`, which
  yields an empty queue that looks exactly like having no applications waiting.

### Fixed

- **A restored course keeps the groups and the role a decision recorded.** The applications
  trail stores which groups an approved applicant was put into and which role they were given,
  and a backup carried neither: restoring a course produced records that said a decision had
  been taken and showed nothing about what it was. Both now travel, translated to the groups
  and roles of the course they land in rather than copied as the numbers they had in the
  course they came from. A group that did not come across — one belonging to a different
  course, say — is dropped rather than pointed at whatever that number means in the
  destination, and a role the restoring user may not assign falls back to the role configured
  on the enrolment method, exactly as an application decided without a role choice does.

  This matters more since the previous entry: those two details are now part of what somebody
  gets when they ask for their own data, so a restored course would have shown them a decision
  with the groups and role silently missing.

- **A subject access request now includes the decision taken on the application.** The plugin's
  privacy declaration has always listed the message a decider wrote to the applicant, the groups
  the applicant was put into and the role they were given — but the export itself carried none of
  the three, so anyone reading their own data was told those details were held and then not shown
  them. They are now exported, to the applicant and to whoever decided alike: the applicant is
  entitled to a record of what was decided about them, and the decider to a record of what they
  decided. The applicant's own comment and submitted details remain theirs alone, as before.

  Groups and roles are exported by name rather than by the internal numbers the record stores,
  and a group or role deleted since the decision is simply left out rather than reported as a
  number that means nothing to the reader.

- **Approving a restored application could fail with a programming error instead of enrolling
  anybody.** The record of a decision stores the groups the decider picked, and a course restore
  copies that list in from wherever the backup was made. Where the list survived the copy but
  named no group this site has — every id in it belonging to a course that was not restored with
  it, say — approving the application raised an error rather than falling back to the groups
  configured on the enrolment method. It now falls back, which is what happens when no groups
  were picked at all.

- **A decision could be applied and then reported as an error.** Where an operator was sent
  after deciding was worked out from whether they could get into the course, rather than from
  whether they could open its approval queue. Two kinds of operator fell in that gap: a mentor
  who is also enrolled in the course as something else, and a teacher whose own enrolment has
  been suspended or has expired — both keep the permission to decide, and neither can open the
  queue. Their decision was taken and applied, and they were then shown a permission error or
  bounced to the course's enrolment page, which reads exactly like the decision having failed.
  Both now go where they can actually get to; an operator who can open no queue at all is sent
  to their own home page rather than to one that will refuse them.

- **Two applications submitted in the same second no longer trade places.** Moodle records an
  application's date to the second, so a cohort admitted by one script — or simply a busy
  minute — leaves several applications sharing a timestamp, and the queue was ordered by that
  date alone. A database is free to return tied rows in any order and need not make the same
  choice twice, so on a queue long enough to page, one application could appear on two pages
  while another appeared on none. Both listings now order by something unique as a last resort,
  whichever heading the operator sorts by.

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
