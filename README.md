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

## Privacy

The plugin stores the comment submitted with an application in
`enrol_apply_applicationinfo`, so it implements the full privacy API: metadata, data
export, and deletion for a user, for a list of users, and for a whole context.

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
