# Enrolment upon approval (`enrol_apply`)

A Moodle enrolment plugin that inserts an approval step into course enrolment. A user
applies for a course, optionally leaving a comment; the enrolment is created suspended,
so it grants no access; a teacher or manager then confirms it, defers it to a waiting
list, or cancels it. Applicants are notified of the decision by message and e-mail.

## Compatibility

| Moodle | Supported | PHP |
|--------|-----------|-----|
| 5.3    | yes       | 8.3 – 8.4 |
| 5.2    | yes       | 8.2 – 8.4 |
| 5.1    | yes       | 8.2 – 8.4 |
| 4.5 and older | no  | — |

Declared in `version.php` as `$plugin->supported = [501, 503]`. CI runs one
moodle-an-hochschulen job per supported branch; the compatibility table and the job list
in `.github/workflows/ci.yml` are updated together with `supported`.

## Features

- Applications are queued for approval instead of enrolling the user immediately.
- A configurable free-text comment field on the application form.
- Optionally collect the standard and the custom user profile fields with the application,
  and ask questions of the method's own (application fields), shown or hidden by an earlier answer.
- A waiting list, distinct from a plain pending application.
- Groups an applicant automatically joins **once the application is approved**.
- A cap on the number of applicants per instance.
- Optionally, core's group enrolment keys: a valid key enrols the applicant at once, without
  review, in that group (and, with `local_groupdist`, only while every group it joins has a seat).
- Notifications to course teachers, to a configurable global recipient list, and to
  mentors holding the capability in the applicant's own user context.
- Enrolment duration with the standard core expiry notifications and expiry action.

## Installation

### From the Moodle plugins directory

Site administration → Plugins → Install plugins, then upload the release ZIP.

### With git

```sh
cd /path/to/moodle/public/enrol      # Moodle 5.x; drop "public/" on 4.x layouts
git clone https://github.com/uaiblaine/moodle-enrol_apply.git apply
```

Then visit Site administration → Notifications and follow the upgrade steps.

## Configuration

Site-wide settings live under Site administration → Plugins → Enrolments → Course enrol
confirmation: the notification templates for confirmation, waiting list and cancellation
mails, who gets notified about new applications, the expiry behaviour, and the defaults
applied to newly created instances.

Per course, add the "Course enrol confirmation" method under Participants → Enrolment
methods. The pending queue for a course is reachable from the enrolment methods page;
the site-wide queue is at Site administration → Courses → Manage enrolment applications
for site administrators, and at `/enrol/apply/manage.php` for anyone else holding
`enrol/apply:manageapplications` at system level.

### Settings

Every setting is stored per plugin, as `enrol_apply/<name>`:

| Setting | What it decides |
|---------|-----------------|
| `allowedfields` | which profile fields a course may ask for |
| `allowprofilewrite` | whether a course may offer to save the submitted details to the profile (off by default) |
| `queuefilterfields` | which identity fields the applications queue offers as filters |
| `confirmmailsubject`, `confirmmailcontent` | the approval message |
| `waitmailsubject`, `waitmailcontent` | the waiting-list message |
| `cancelmailsubject`, `cancelmailcontent` | the cancellation message |
| `notifyglobal` | who is told about every new application, site wide |
| `retentiondays` | how long an application record is kept (30 days by default, 0 for ever) |
| `expiredaction`, `expirynotifyhour` | what happens when an enrolment period ends, and when expiry notices go out |
| `defaultenrol`, `status`, `newenrols`, `groupkeys`, `opt_commentaryzone`, `maxenrolled`, `places`, `roleid`, `notifycoursebased`, `enrolperiod` | the defaults of a new instance |

### Group enrolment keys

An instance can accept the enrolment key of one of the course's groups ("Accept group
enrolment keys", off by default; the site default for new instances is
`enrol_apply/groupkeys`). The key is core's own group enrolment key, set on each group's
settings page, the one `enrol_self` reads; this plugin stores none. The instance form lists
which groups carry a key, never the keys.

With the option on, the application form starts with an optional **Group enrolment key**. An
applicant who enters a valid key still fills in every required field and question, and on
submitting is enrolled at once and joins that group (and the instance's own groups), with no
review, and goes straight into the course. The enrolment window, the cohort restriction, the
applicant limit and the places still apply: with no place left, the application waits for a
review instead, and the applicant is told why. A wrong key is refused with the choice to send
the application for review without it.

Wrong keys are counted per applicant and instance in the `keyattempts` application cache,
for 15 minutes; after five, the field gives way to a sentence for the rest of that time, so the
form cannot be used to guess keys without limit. The key is compared as typed, with no
trimming, and is read again inside the lock that writes the enrolment, so rotating a key on the
group's page stops it at once.

Who is told what:

- **The applicant** reads "Enrolled with your group key", naming the group, on the
  acknowledgement page; no "your application was approved" message is sent,
  since nobody reviewed it. A valid key that could not admit says why: no place left, the group
  full, or a place that could not be confirmed in time.
- **Managers** (the same people told about new applications) get an informational notice with
  its own subject and a link to the applicant. It names the group only to a recipient who may
  manage applications in the course.
- **The queue and the review page** mark an application a valid key could not admit. The review
  page names the group and offers ticked the groups the key would have joined.
- **The reports** carry an **Admission route** column and filter (review, or what the key did)
  and the **Group of the enrolment key**.

**Optional: `local_groupdist`.** When it is installed, each group's **Seats** (a group custom
field it provides; empty means no limit, 0 means full) count too. A key admits only while every
group the admission joins (the key's and the instance's own) has a seat; otherwise the
application waits for a review. The instance form shows each keyed group's seats, the approval
group chooser shows every group's occupancy (a full group can still be chosen: a person decides),
and an approval that takes a group past its seats is followed by a warning. Occupancy is every
member of the group, counted as `local_groupdist` counts it. Without it, groups carry no seats
and nothing above applies. It is not declared as a dependency.

### Placeholders in the notification mails

`{firstname}`, `{lastname}`, `{username}`, `{content}` (the course name) and `{timeend}`
(the enrolment expiry date) are replaced when the message is sent.

## Capabilities

| Capability | Grants |
|------------|--------|
| `enrol/apply:config` | add, edit and remove instances |
| `enrol/apply:manageapplications` | decide on applications |
| `enrol/apply:viewreports` | read the course's report of applications |
| `enrol/apply:manage` | manage the resulting user enrolments |
| `enrol/apply:unenrol` | unenrol other users |
| `enrol/apply:unenrolself` | unenrol yourself |

`enrol/apply:manageapplications` is honoured at three levels: at system level it covers
every course, at course level that course, and in a user's own user context it covers
that user's applications, which is what allows mentor-style delegation.

`enrol/apply:viewreports` is granted to managers only, and deliberately not to editing
teachers the way the capabilities around it are. The report it opens shows the profile
details every applicant submitted, for every application the course has ever had, decided
ones included and long after the enrolment itself has gone — a wider disclosure than
deciding on the queue in front of you. A site that wants its teachers to have it should
grant it to them on purpose. It carries `RISK_PERSONAL`, so Moodle flags it as such on the
role definition screens.

Which profile details appear is a second, separate question, and the answer is core's:
a reader without `moodle/site:viewuseridentity` in the course sees the applicant's name and
nothing else. The identity fields are not blanked for them, they are absent — no column, no
filter, no sort — because filtering and sorting are database operations and would otherwise
let a reader recover a hidden value by narrowing on it and counting the rows.

The details an applicant submitted follow the same rules on the queue, the review page, the
course report and the approver's e-mail, judged for each reader in the course: the address,
phone numbers, institution and department appear only where *Show user identity*
(`showuseridentity`) names them; a field listed in *Hide user fields* (`hiddenuserfields`), such
as the city or country, needs `moodle/course:viewhiddenuserfields`; and a custom profile field
whose visibility is private or nobody needs `moodle/user:viewalldetails`. To let approvers read a
department they asked for, add it to *Show user identity*.

## The applications report

Course → Participants → Enrolment methods carries a report icon beside each Course enrol
confirmation method, and the course settings navigation carries the same link. It lists
every application the course has recorded — who applied, when, the comment, the profile
details submitted, the outcome, whether a group enrolment key admitted it, who decided it and
when — with per-user filters, sorting,
paging and a CSV or Excel download.

Where a course carries more than one Course enrol confirmation method, the report opens scoped to
the one whose icon you clicked, clearing the method filter widens it to every application in the
course, and each method remembers your filters separately. A course with a single method offers no
such filter, and its report lists that course's applications as it always did.

## Site-wide custom reports

Site administration → Reports → Report builder offers **Enrolment applications** as a report
source, for building ad-hoc reports across every course. It adds a course filter and a course
name column that the course report deliberately does not have.

The profile details an applicant submitted appear here only for a reader holding
`moodle/user:viewalldetails`, and for anyone else the column is absent rather than empty — it
cannot be added to a report, filtered or sorted. That is a deliberately different gate from the
course report's, because a custom report has no course to ask about and no permission check of
this plugin's on it at all: who may read one is decided by Moodle's report capabilities and by
the report's audience.

Two consequences worth knowing before relying on it. A report can be shared with an audience
wider than the person who built it. And a **scheduled** report is rendered once using its
creator's permissions and then mailed to every recipient, so what arrives in an inbox reflects
what the creator may see, not what the reader may.

This surface follows the site's `Enable custom reports` setting. The course report does not —
it keeps working when custom reports are switched off.

### Large exports, and exports on a schedule

Both reports download synchronously, and that is deliberate rather than a limitation to work
around. Moodle's export path sets no time limit and streams rows one at a time rather than
loading them all, so a large export takes longer but does not run out of time or memory.

If you want an export to arrive **on a schedule** rather than on demand, build a custom report
on the Enrolment applications source and use Report builder's own scheduling: Moodle produces the
file, mails it and cleans it up, with nothing needed from this plugin.

One limit worth knowing before planning around it: Moodle can only schedule **custom** reports,
and who may edit them is decided at site level. So a course manager cannot be given a recurring
export of their own course's applications without also being given site-level report permissions.
For a one-off, the course report's own download is the answer.

### Setting up a mentor

A mentor is somebody who decides on the applications of specific users, wherever those
users apply, without holding the capability in the course or at system level. Moodle's
standard mentor pattern configures this in three steps:

1. **Site administration → Users → Permissions → Define roles → Add a new role.** Give it
   a name such as "Application mentor" and allow `enrol/apply:manageapplications`.
2. On the same form, under **Context types where this role may be assigned**, tick
   **User**. Without this the role cannot be attached to a person.
3. Open the mentee's profile and use **Preferences → Roles → Assign roles relative to
   this user** to give the mentor that role.

The mentor then opens `/enrol/apply/manage.php` with no parameters, sees exactly those users'
applications there, and can decide on them. (The Site administration menu entry is only shown to
site administrators.)

One limitation is worth knowing before you rely on it. A Moodle capability declares a
single context level, and this one declares `CONTEXT_COURSE`; the user-context override
screen lists only capabilities declared at `CONTEXT_USER`, so this capability does not
appear there. Defining and assigning the role works exactly as described above — what you
cannot do is override the capability for one individual mentee. Core lives with the same
constraint for `moodle/grade:viewall`, which its own declaration marks as
"CONTEXT_COURSE // and CONTEXT_USER".

## The record of an application

Every application leaves a durable record in `enrol_apply_submission`: what the applicant
wrote, the profile details they entered, the decision taken, who took it and when, and, when
a group enrolment key was entered, which group's key it was and what it did (admitted without
review, or left the application waiting because no place or seat was left; the key itself is
never stored). The
pending comment in `enrol_apply_applicationinfo` is deleted on approval, on cancellation and
on unenrolment — deferring an application to the waiting list keeps it, because the
application is still awaiting a decision. This record survives all four, and, from this
release, **deleting the enrolment method no longer removes it either**.
Removing an enrolment method is a change to the course's configuration; the record of who
applied and what was decided is not part of that configuration.

Three things do end it:

- **Deleting the course.** The records are kept but stripped of everything personal: both
  user ids are zeroed and the comment and profile snapshot are emptied, leaving only the
  dates and the outcome. This happens before the course context is destroyed, because
  afterwards no subject access or erasure request could reach the rows at all.
- **An erasure request**, differently for each of the two roles. A record the person
  *submitted* goes outright: erasure wins over permanence here on purpose, because the trail
  exists to tell a manager what was decided, not to be evidence against the person it
  describes. A record they merely *decided* keeps everything except their name — the record
  belongs to the applicant and carries the applicant's own words, so erasing the decider must
  not take somebody else's data with it.
- **The retention period.** *Site administration ▸ Plugins ▸ Enrolments ▸ Course enrol
  confirmation* offers **Keep application records for**, 30 days by default. A daily scheduled
  task deletes records older than that — decided or abandoned alike, because an application
  nobody ever looked at is exactly the kind that would otherwise be kept forever. The one
  exception is a record whose application is **still in the queue**: nothing expires a pending
  application, so age alone does not make it finished, and deleting its record would leave the
  decision taken later with nothing to record it against. Set the period to zero to keep
  everything forever.

Two consequences worth knowing before you rely on a backup:

- A record travels **only when the backup includes users** — and which switch decides that
  depends on how the backup was made. A manual backup is governed by the wizard's *Include
  enrolled users* checkbox, defaulted from *Site administration ▸ Courses ▸ Backups ▸ General
  backup defaults*. The recycle bin is not: it backs up in automated mode, so the switch that
  governs it is *Automated backup setup ▸ Include users*. Turn that one off and a course that
  goes through the recycle bin comes back without its application records.
- A record travels **only while its enrolment method still exists**. Backup data for an
  enrolment plugin is written per enrolment method, so a record kept after the method was
  deleted has nothing to attach itself to and stays behind. It is still on the site, still
  reachable by a privacy request and still swept by the retention period — it simply does not
  follow the course into a copy.

## Data stored

Five tables. Three hold personal data:

- `enrol_apply_applicationinfo` — the comment written with an application still awaiting a
  decision, keyed by the user enrolment. Deleted on approval, on cancellation, on unenrolment,
  with the enrolment method and with the course.
- `enrol_apply_submission` — the durable record of each application: the applicant, the comment,
  the decision, its message to the applicant, the decider's note, the groups and role chosen, who
  decided and when, and the group whose enrolment key was entered with what it did (never the key). Kept for the retention period (`enrol_apply/retentiondays`, 30 days by
  default; a record still in the queue is never swept), pseudonymised when the course is deleted,
  and kept when the enrolment or the method is deleted. See "The record of an application" above.
- `enrol_apply_answer` — what the application submitted: one row per profile field and per
  question asked, with the question as it read at submission. Removed with its record, by every
  route that removes one.

Two are configuration and name nobody: `enrol_apply_groups` (the groups an approved applicant
joins, per method) and `enrol_apply_fieldcond` (when a question is shown). Both are deleted with
the method.

An approval also writes core rows: a role assignment and group memberships stamped with
`enrol_apply`, and the enrolment itself. The plugin never writes the applicant's profile unless
the site allows it (`enrol_apply/allowprofilewrite`) and the applicant accepts the offer.

## Privacy

The plugin implements the full privacy API — metadata, data export, and deletion for a
user, for a list of users, and for a whole context — over its three personal-data tables and
the group memberships its approvals add.

- **A data export** gives the applicant their pending comment, every application record they
  submitted with the details and answers it carried and the decision taken on it, and the groups
  an approval added them to. Somebody who decided applications gets each decision they took,
  never the applicant's comment or answers.
- **A deletion** removes the applicant's comments, records and answers and the group memberships
  approvals gave them in the course; for a decider it only clears their name from the records,
  which stay the applicant's. Role assignments and the enrolment itself are core's, covered by
  core's own privacy providers.

## Development

See `CLAUDE.md` for the architecture notes and the local development commands.

```sh
mdl ci moodle-enrol_apply          # the full CI pipeline, locally
mdl phpunit m502 enrol_apply
mdl behat m502 @enrol_apply
```

## Credits

Originally written by [emeneo](http://emeneo.com/) and contributors, with substantial
work by Johannes Burk (sudile GbR), Romain Deleau (IMT Lille Douai), Esdras Caleb and the
community translators of the earlier releases. This fork continues from
[emeneo/moodle-enrol_apply](https://github.com/emeneo/moodle-enrol_apply).

## Translations

The package ships English only, as the Moodle plugins directory requires. Translations reach
sites through [AMOS](https://lang.moodle.org/), Moodle's translation platform. The repository
keeps a Brazilian Portuguese pack in `lang/pt_br` for development; it is left out of the release
package and is contributed to AMOS instead.

## License

GNU GPL v3 or later; see `LICENSE`.
