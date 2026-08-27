# What the audit report can and cannot say, and whether to lock the participants page

Written 2026-08-24, after two symptoms were reported from a live site: an enrolment approved from
the participants list is recorded correctly, but **an unenrolment leaves the record reading
"Pending"**, and **a manual suspension puts the application back in the approval queue while the
report goes on saying "Approved"**.

Both reproduce. Everything below was measured on m501 (Moodle 5.1) and m502 (Moodle 5.2) unless a
line says otherwise, and every core citation was checked on both branches.

## The one-sentence diagnosis

The report answers *"what decision did this plugin's own state machine last record"*. It does not
answer *"what happened to this application"*, and those two questions stopped having the same
answer the moment anything outside the plugin's queue could change an enrolment.

## Every route that changes an apply enrolment

`submission::decide()` is reached from exactly three call sites — `complete_approval()`
(`lib.php:364`), `wait_enrolment()` (`lib.php:952`) and `cancel_enrolment()` (`lib.php:996`).
Every other route changes the enrolment and leaves the durable record where it was.

| Route | `complete_approval()` | `decide()` | record ends at |
|---|---|---|---|
| Apply | no | no (`create()` writes PENDING) | PENDING |
| Queue: approve | yes, **twice** | yes → APPROVED (first pass) | APPROVED |
| Queue: defer | no | yes → WAITING | WAITING |
| Queue: cancel | no | yes → CANCELLED (before the unenrol) | CANCELLED |
| Participants "Edit enrolment" → Active | yes (hook), **only if an `applicationinfo` row survives** | yes → APPROVED | APPROVED |
| Participants "Edit enrolment" → Suspended | no (`hook_callbacks.php:66` returns) | no | **unchanged** — and the row re-enters the queue |
| Participants "Edit enrolment", dates only | no (`statusmodified` false) | no | unchanged |
| Participants unenrol | no | no | **unchanged** |
| Self-unenrol (`unenrolself.php:53`) | no | no | unchanged |
| `process_expirations`, unenrol | no | no | APPROVED, enrolment gone |
| `process_expirations`, suspend / suspendnoroles | no | no | APPROVED, **not** re-queued (`timeend > 0`) |
| `delete_instance()` | no | no | unchanged, enrolment gone |
| `delete_course()` | no | no | status unchanged, row pseudonymised |
| `delete_user()` | no | no | unchanged |
| Restore | no | no | status from the archive; `decidedrole`/`decidedgroups` **lost** |
| `purge_submissions` | — | — | row deleted once out of the queue and past retention |

Two routes that do **not** exist and were checked rather than assumed: the plugin declares no bulk
operations, and it ships no `db/services.php`, so there is no web-service route either.

## Symptom 1 — unenrolment, and the narrowing that matters

`db/hooks.php` registers only `before_user_enrolment_updated` and `before_course_deleted`. Core
**does** dispatch `\core_enrol\hook\before_user_enrolment_removed` from
`enrol_plugin::unenrol_user()` itself (`lib/enrollib.php:2312`, same line on both branches), so it
fires for the participants page, course reset, user deletion and expiry alike. The plugin is
simply not listening: `unenrol_user()` (`lib.php:1298-1310`) deletes the `applicationinfo` row and
never touches the durable record.

**But a record surviving an unenrolment is deliberate, and already pinned.**
`tests/lib_test.php::test_a_submission_row_survives_unenrolment` approves, unenrols, and asserts
the record is still APPROVED with the enrolment's absence as its control;
`implementation-plan.md:1019-1020` specifies it. So "record every unenrolment as an outcome" is
the wrong fix — it would break intended behaviour.

The genuinely broken case is narrower: **a PENDING application that is unenrolled reads "Pending"
for ever and is reachable from no screen.** It has no `user_enrolments` row, so it is not in the
queue; the report shows it as awaiting a decision; and `purge_submissions` eventually deletes it
without ever correcting it.

## Symptom 2 — suspension, and what is genuinely new about it

`hook_callbacks.php:66` returns early for any status that is not `ENROL_USER_ACTIVE`. That is
deliberate on the *enrolment* side and the plugin already documents it — `manage_table.php:100-104`
says in terms that "a decided enrolment can come back to this queue: suspending an approved
participant from core's participants page leaves status != active with timeend = 0, which is
exactly the predicate above", and `PROGRESS.md:606-611` names the same route.

What is new, and is the actual complaint, is only that **the report does not follow**. The queue
says "awaiting a decision"; the report says "Approved"; neither mentions the other.

## Two defects found while measuring, both worse than the reported symptoms

**A re-approval notifies the applicant twice and the trail denies the second decision.** Measured
on both branches: approve → one adhoc task → one message. Suspend by hand, approve again → a
second task → a **second** message, while `decide()`'s same-status skip leaves `status`,
`timedecided` and `decidedby` naming the **first** decider. Task deduplication cannot help,
because the first task has already left `{task_adhoc}`.

**The chosen groups and the outcome message are sticky.** `record_decided_groups()` and
`record_outcome_message()` both return early on an empty value, so no path can clear a stored one:
approve, have the row re-suspended, approve again with the controls left alone, and the applicant
silently keeps the earlier group list and receives the earlier message again. `record_decided_role()`
deliberately does not copy that shape, and
`tests/decision_role_test.php::test_a_later_approval_clears_an_earlier_choice` pins the difference.

## The scenario matrix, and why the fix is read-side

Every cell below is computable **today**, from data the record already holds plus one
`LEFT JOIN {user_enrolments} ue ON ue.id = s.userenrolmentid`. `userenrolmentid` is already a base
field of the system report (`course_applications.php:125`).

| stored status | live enrolment | truthful outcome |
|---|---|---|
| Pending | present | Awaiting a decision |
| Pending | gone | **Never decided — the applicant is no longer enrolled** |
| Approved | active | Approved, enrolled |
| Approved | suspended, `timeend = 0` | **Approved, then suspended — back in the queue** |
| Approved | suspended, `timeend` in the past | **Approved, then expired** |
| Approved | gone | **Approved, then unenrolled** |
| Waiting | status 2 | On the waiting list |
| Cancelled | gone | Cancelled |

This is the whole point of the analysis: **the record is not wrong, the report was lying by
omission.** A read-side fix needs no new status values, no new writers and no schema change, and
it therefore cannot break the pinned behaviour above, cannot overwrite `cancel_enrolment()`'s
CANCELLED, and cannot make the double-notification defect worse.

Three caveats, all measured:

- A restore writes `userenrolmentid = 0` when it cannot map the enrolment
  (`restore_enrol_apply_plugin.class.php:113` casts a false mapping to 0). Zero must render
  **"Unknown"**, never "no longer enrolled" — that would be a fresh falsehood of exactly the kind
  this work exists to remove.
- The derived outcome is a display callback, and **filtering and sorting are SQL** — they never
  reach a callback. Either the underlying `ue.status`/`ue.timeend` get their own columns, or the
  derived value must be computed in SQL. Shipping a sortable derived column that sorts by
  something else is the trap.
- `decidedrole` and `decidedgroups` are absent after a restore, so they render "—" and not
  "none chosen".

### What a single `status` column can never say

"Approved, then suspended, then re-approved, then unenrolled" does not fit in one mutable column;
it can only hold the last word. Extra status values (`UNENROLLED`, `SUSPENDED`, `EXPIRED`) are
attempts to work around that ceiling and each carries its own trap — an unconditional
"unenrolled" writer would overwrite the plugin's own Cancel on every cancellation, because
`cancel_enrolment()` stamps CANCELLED and only then unenrols (`lib.php:996-1002`).

If the full history is ever wanted, the shape is a decision log
(`submissionid`, from-status, to-status, actor, time, route, message), not more vocabulary. That
would also fix the re-decision problem without touching `decide()`'s same-status skip — which
cannot simply be deleted, because it is what makes `complete_approval()` running twice safe.

`\core\event\user_enrolment_updated` and `user_enrolment_deleted` mean a site keeping the standard
log already holds this history, but the logstore is optional and separately purged, so it is
usable as a "view in the log" link and not as a report source.

## Can the participants page be locked?

Yes, and it is less work than it looks — but the full version is not recommended.

**The base class denies by default.** `enrol_plugin::allow_enrol()`, `allow_unenrol()`,
`allow_unenrol_user()` and `allow_manage()` all return false (`lib/enrollib.php:2005`, `:2016`,
`:2032`, `:2044` — same lines on both branches). `enrol_cohort` gets the behaviour by simply not
overriding them, plus one narrow `allow_unenrol_user()` exception for an already-suspended row.

**`enrol_apply` explicitly opted in, and inherited that from upstream.** `lib.php:70` returns true
from `allow_unenrol()`, `lib.php:149` from `allow_manage()`. `git log -S` puts the second in
`c9aa093` "Implements enrolments management" (2018-02-24), and `git merge-base --is-ancestor
c9aa093 867e248` confirms it predates this fork. Neither is a decision this fork made.

**Upstream already tried the lock and backed it out.** Until `51fb19b`, `lib.php` carried a
commented-out `allow_unenrol_user()`:

    if ($DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => $ue->id])) {
        return false; // This line cause some issues with the unenrol of some users
                      // without resolving the application first.
    }

Note the predicate keys on `enrol_apply_applicationinfo`, which is **deleted on approval**, so it
would only ever have blocked unenrolling *pending* applicants. That may well be why it "caused
issues": wrong predicate for the intent.

**The enforcement is server-side, on all three routes.** This was the question worth answering
before anything else, and the answer is yes: `enrol/editenrolment.php:50` calls `redirect()`
before `require_login()`; the participants modal's web service hands the write to
`course_enrolment_manager::edit_enrolment()`, which re-checks (`enrol/locallib.php:943`) and
returns false without writing; and `enrol/unenroluser.php:54` throws. Measured in-process on both
stacks by swapping a locked subclass into the manager's plugin registry: as shipped
`edit_enrolment() = true / unenrol_user() = true`; with the gates false, both false and the row
unchanged.

### Why `allow_manage() = false` is the wrong half to take

- **It reverses a documented decision in the file it would keep.** `hook_callbacks.php:41-43`:
  the observer exists "rather than trying to forbid that path — `enrol/apply:manage` is core's
  'may edit enrolments' capability and denying it would remove legitimate date editing too."
- **That is correct.** The participants modal is the only UI for `timestart`/`timeend` on an
  approved applicant. The queue stamps dates once, at approval, and `manage_table.php:69` excludes
  active rows, so an approved row never comes back to be edited.
- **It cannot be narrowed.** `allow_manage(stdClass $instance)` never sees the user enrolment, so
  it cannot distinguish a pending row from an approved one. By contrast
  `allow_unenrol_user(stdClass $instance, stdClass $ue)` **does** receive the row — per-row policy
  is structurally available on the unenrol side and structurally unavailable on the manage side.

### What `allow_unenrol() = false` silently breaks

- **Course reset stops unenrolling apply participants.** `reset_course_userdata()` does
  `if (!$plugin->allow_unenrol($instance) and !$plugin->allow_unenrol_user($instance, $ue)) { continue; }`
  (`lib/moodlelib.php:5335` on 5.2, `:5262` on 5.1). "Unenrol users" would silently skip every
  apply enrolment.
- **Restore-with-delete-existing-contents stops deleting the instance.** `enrol_course_delete()`
  filters instances by `allow_unenrol()` when a userid is passed (`lib/enrollib.php:1184`), and
  `restore_dbops::delete_course_content()` passes one. The apply instance, its `user_enrolments`
  **and** its component-stamped `role_assignments` all survive a restore meant to wipe them
  (`moodlelib.php:4957` unassigns only `component = ''`). Ordinary full course deletion passes
  `null` and is unaffected.

Neither has a plugin-side workaround.

### What does not break

The plugin's own state machine is untouched: `update_user_enrol()` performs no `allow_manage()`
check and `unenrol_user()` performs no `allow_unenrol()` check, so `confirm_enrolment()`,
`wait_enrolment()`, `cancel_enrolment()` and `process_expirations()` all keep working. The
"Unenrol me" link survives — `get_unenrolself_link()` gates on the file existing, the instance
being enabled, `enrol/apply:unenrolself` and an **active** enrolment, never on `allow_unenrol()`.
The participants bulk menu is unaffected, though not for the obvious reason: it belongs to
`enrol_manual`, and what excludes apply rows is the instance filter
(`user/action_redir.php:188` + `course_enrolment_manager`'s `instancefilter`), not any property of
this plugin.

Keep the `enrol/apply:manage` capability whatever happens: `get_enroller()` (`lib.php:1411`) uses
it to pick the notification's "from" user.

### The custom action icon

> **Superseded on 2026-08-27, when the icon was built. Do not follow this section's
> instructions.** Three of its statements are now wrong, and the first two were wrong at the
> time in ways only the build measured:
>
> - **The target.** It says the link "must point at `manage.php?id=<enrolid>` and **not**
>   `?userenrol=`" because that branch authorised at the applicant's user context. That gate was
>   replaced by `\enrol_apply\local\queue::require_review_access()`, which applies
>   `can_manage_application()` and admits the course teacher. The icon ships pointing at
>   `?userenrol=`, because it decides ONE application and `?id=` opens the whole queue.
> - **"Any other `data-action` is left to the browser."** Two modules claim names in this markup,
>   not one: the participants table is a `core_table\dynamic` table, and `core_table/dynamic`
>   also intercepts `a[data-action="hide"]`, `a[data-action="show"]` and `[data-action="showcount"]`
>   anywhere inside `[data-region="core_table/dynamic"]`. The shipped link carries no
>   `data-action` at all.
> - **The markup block below is not what shipped**, despite its "Measured markup on m502"
>   heading — it was a sketch. The real rendered anchor, measured on m502 for user enrolment 394:
>   `<a href="…/enrol/apply/manage.php?userenrol=394" role="button" title="Decide this application"><i class="icon fa fa-clipboard-user fa-fw" title="Decide this application" role="img" aria-label="Decide this application"></i></a>`
>
> What this section got right and the build kept: no core enrol plugin overrides the method, and
> the icon needs a status gate or it renders on approved rows. See `CLAUDE.md` and
> `enrol_apply_plugin::get_user_enrolment_actions()` for what was actually built.

A plugin **can** add its own icon to the participants page by overriding
`get_user_enrolment_actions()`, and it renders as an ordinary link:
`user/amd/src/status_field.js` intercepts only `editenrolment`, `unenrol` and `showdetails`, so any
other `data-action` is left to the browser. Measured markup on m502:

    <a href="…/enrol/apply/manage.php?id=140" role="button" class="enrol_apply_decidelink"
       data-action="enrol_apply_decide" title="Decide this application">…</a>

Three things to know. **No core enrol plugin does this** — a search of `enrol/`, `mod/` and
`blocks/` finds no override and no other `user_enrolment_action` construction, so there is no
precedent to copy. It must point at `manage.php?id=<enrolid>` and **not** `?userenrol=`: that
branch authorises at the *applicant's user context*, where a course teacher measurably fails
(`manageapplications` course ctx = true, applicant user ctx = false). And it needs a status gate,
or it renders on already-approved rows too.

### A core defect found in passing

The participants page renders a waiting-list row (`ENROL_APPLY_USER_WAIT = 2`) as a green
**"Active"** badge. `user/classes/table/participants.php` pre-sets Active before its switch, and
the switch has cases for `ENROL_USER_ACTIVE` and `ENROL_USER_SUSPENDED` with **no default arm**, so
2 falls through. This is core's code, cannot be fixed from the plugin, and is a second independent
reason the participants page and the plugin's report disagree.

## Recommendation

1. **Do the read-side report work first, and possibly only that.** It answers both reported
   symptoms, needs no new writers, and cannot regress the pinned behaviour. It is the mandatory
   half; locking prevents future divergence and records nothing about what has already happened.
2. **Do not set `allow_manage() = false`** until the queue can edit an approved enrolment's dates.
   It is whole-screen by construction and would take a working capability away.
3. **`allow_unenrol_user()` is where a lock belongs if one is wanted**, because it is the only one
   of the four that sees the row. `$ue->status != ENROL_USER_ACTIVE` matches this plugin's house
   predicate everywhere else; `enrol_cohort`'s literal `== ENROL_USER_SUSPENDED` would strand the
   waiting list (measured: no actions at all on a status-2 row). Weigh it against the course-reset
   and restore-with-delete regressions, which are silent.
4. **The two write-side defects** — the double notification on re-approval, and the sticky groups
   and message — are independent of all of the above and worth their own change.

## Nothing in the pipeline reads any of this

phpcs, phpdoc, the mustache lint and eslint cannot tell that `allow_manage()` returns the wrong
constant, and the Behat suite never visits `user/index.php`. If any of these overrides land they
need a PHPUnit test asserting each return value **and** one asserting that
`course_enrolment_manager::edit_enrolment()` and `unenrol_user()` return false — mutation-checked,
because a test that merely calls the methods passes against the current `true`.
