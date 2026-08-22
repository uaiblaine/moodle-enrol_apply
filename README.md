# Enrolment upon approval (`enrol_apply`)

A Moodle enrolment plugin that inserts an approval step into course enrolment. A user
applies for a course, optionally leaving a comment; the enrolment is created suspended,
so it grants no access; a teacher or manager then confirms it, defers it to a waiting
list, or cancels it. Applicants are notified of the decision by message and e-mail.

## Compatibility

| Moodle | Supported | PHP |
|--------|-----------|-----|
| 5.2    | yes       | 8.2 – 8.4 |
| 5.1    | yes       | 8.2 – 8.4 |
| 4.5 and older | no  | — |

Declared in `version.php` as `$plugin->supported = [501, 502]`. CI runs one
moodle-an-hochschulen job per supported branch; the compatibility table and the job list
in `.github/workflows/ci.yml` are updated together with `supported`.

## Features

- Applications are queued for approval instead of enrolling the user immediately.
- A configurable free-text comment field on the application form.
- Optionally collect the standard and the custom user profile fields with the application.
- A waiting list, distinct from a plain pending application.
- Groups an applicant automatically joins **once the application is approved**.
- A cap on the number of applicants per instance.
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
the site-wide queue is at Site administration → Courses → Manage enrolment applications.

### Placeholders in the notification mails

`{firstname}`, `{lastname}`, `{username}`, `{content}` (the course name) and `{timeend}`
(the enrolment expiry date) are replaced when the message is sent.

## Capabilities

| Capability | Grants |
|------------|--------|
| `enrol/apply:config` | add, edit and remove instances |
| `enrol/apply:manageapplications` | decide on applications |
| `enrol/apply:manage` | manage the resulting user enrolments |
| `enrol/apply:unenrol` | unenrol other users |
| `enrol/apply:unenrolself` | unenrol yourself |

`enrol/apply:manageapplications` is honoured at three levels: at system level it covers
every course, at course level that course, and in a user's own user context it covers
that user's applications, which is what allows mentor-style delegation.

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

The mentor then sees exactly those users' applications at Site administration → Courses →
Manage enrolment applications, and can decide on them.

One limitation is worth knowing before you rely on it. A Moodle capability declares a
single context level, and this one declares `CONTEXT_COURSE`; the user-context override
screen lists only capabilities declared at `CONTEXT_USER`, so this capability does not
appear there. Defining and assigning the role works exactly as described above — what you
cannot do is override the capability for one individual mentee. Core lives with the same
constraint for `moodle/grade:viewall`, which its own declaration marks as
"CONTEXT_COURSE // and CONTEXT_USER".

## The record of an application

Every application leaves a durable record in `enrol_apply_submission`: what the applicant
wrote, the profile details they entered, the decision taken, who took it and when. The
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

## Privacy

The plugin implements the full privacy API — metadata, data export, and deletion for a
user, for a list of users, and for a whole context — over both of its personal-data tables.
`enrol_apply_submission` names two people rather than one: the applicant, and whoever
decided the application. Both roles are exported and both are erased.

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

The repository ships English and Brazilian Portuguese only. Other languages are welcome
through [AMOS](https://lang.moodle.org/), Moodle's translation platform, which is where
translations for a published plugin belong.

## License

GNU GPL v3 or later. See the header of any source file.
