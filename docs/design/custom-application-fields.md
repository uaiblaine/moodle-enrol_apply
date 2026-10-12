# Application fields: questions owned by the enrolment method

Status: **decided 2026-10-12, in progress.** This document records the design chosen
for fields that an `enrol_apply` instance asks at application time, on top of the
requested profile fields that already exist. It names the core facts the design rests
on, the decisions the owner took, the ones taken by default, and the stages to build
it in.

## The problem

Today an instance can request **profile fields** (`customtext4`, resolved by
`classes/local/fields.php`). Their values are frozen into
`enrol_apply_submission.userinfodata` at submission, and the applicant can opt in to
writing them to the profile (`profilewriter`). That covers "who are you". It does not
cover questions that belong to *this application*, for example a motivation, a
shift preference, an employee number checked against a pattern, or an answer that
only matters if an earlier answer was "yes". Such questions must not become profile
fields: they would pollute every user's profile, be visible site-wide, and be shared
by every course.

## Options considered

| | A. Core custom fields, end to end | B. Own field engine | **C. Hybrid (chosen)** |
|---|---|---|---|
| Field definitions | core `customfield_*` with a handler | own table and builder | core `customfield_*` with a handler |
| Configuration UI | core's | to build | core's, plus a conditions section |
| Stored value | live `customfield_data` row | frozen in the snapshot | **frozen in the snapshot** |
| Renamed or deleted field | history changes or is lost | unaffected | unaffected |
| Regex, mask | not in core | own | a new `customfield_pattern` type |
| Conditions | not in core | own | own table, own evaluator |

`local_ivform` was read as a candidate base for B and rejected: it owns no tables
(definitions are interactive-video annotation content, answers go to
`interactivevideo_log`), its builder depends on `mod_interactivevideo`'s jQuery UI
and modal framework, answers are rendered in JavaScript, it has no input mask at all,
and it is vendor code. Its `local\regex_helper` and `local\hideif_evaluator` are
useful *references* for the same-pattern-on-both-sides check and for skipping a
required field that is hidden; nothing is copied without checking the vendor's terms.

## Why C

The plugin's central invariant is that **every reader of an application reads the
frozen snapshot, never live data** (repo `CLAUDE.md`, "the snapshot is read frozen";
renderer `snapshot_context()`). The approval queue, the review page, the course
report, the custom-report datasource, the notification e-mail, the privacy export and
backup all already read `userinfodata`. Option C puts the new answers into that same
envelope, so every one of those readers shows them with no new storage, no new
masking path and no new privacy table. Core supplies what is expensive to build well:
field types, the configuration UI, the form elements and their validation.

## Core facts this rests on (verified in source on 5.1 and 5.2)

- `core_customfield\handler` takes an **`itemid`** (`handler::create($itemid)`,
  `get_handler($component, $area, $itemid)`). Component `enrol_apply`, area
  `application`, `itemid` = the enrol instance id gives each instance its own fields.
- **Shared categories** (5.1+): `api::get_categories_with_fields()` merges the
  site's shared categories into every handler's list, and
  `api::is_shared_category_enabled($categoryid, $component, $area, $itemid)` filters
  them **per itemid**. The toggle web service (`toggle_shared_category`) checks only
  `$handler->can_configure()`. A site library of fields ("Documents: CPF, RG"), each
  instance switching categories on or off, comes free.
- `handler::get_available_field_types()` is overridable: the handler can restrict
  types.
- `handler::config_form_definition()` runs **after** core's `required` and
  `uniquevalues` elements are added (`field_config_form.php:79-92`), so the handler
  can remove an element core added. It does **not** run for fields of a shared
  category, whose handler is `shared_handler`.
- Every type's `instance_form_definition()` adds its own `required` rule
  (`addRule(..., 'required', null, 'client')`), which QuickForm also enforces server
  side. A hidden-by-condition required field therefore fails validation unless the
  rule is removed. Core removes rules the same way in
  `grade/classes/form/add_category.php:415-417` (`unset($mform->_rules[...])` and the
  `_required` entry).
- `data_controller::create(0, $record, $field)` builds a controller **without a DB
  row**, and `export_value()` formats from `get_value()`. Formatting a submitted value
  for the snapshot needs no `customfield_data` row.
- `uniquevalues` compares against `customfield_data` for the field
  (`data_controller::is_unique()`). C writes no `customfield_data`, so uniqueness has
  nothing to compare against.
- The report builder's `custom_fields` helper is built for one handler and one
  `itemid`; it cannot give one column across instances with different local fields.
  C does not need it: the snapshot column already carries the answers.
- Core field types on every stack: checkbox, date, number, select, text, textarea.
  `picture` on 5.1/5.2 is the fleet's own fork, not core.

## Decisions

Owner decisions (2026-10-12):

- **D1. Option C.** The snapshot is the record; no `customfield_data` is written.
- **D2. Teachers configure the fields.** `handler::can_configure()` is
  `enrol/apply:config` in the instance's course context; the same check lets them
  switch shared categories on or off for their instance. Shared categories
  themselves are defined by whoever core lets manage them (site level).
- **D3. A `customfield_pattern` type is built** (separate repository): single-line
  text with a regular expression, an error message, and an input mask (presets CPF,
  CNPJ, CEP, Brazilian phone, plus a free mask). It is a site-wide field type, so
  course, group and report custom fields can use it too.
- **D4. Conditional fields are built**: a field shows only when an earlier field has
  a given answer.
- **D5. Application fields never become profile data.** The existing requested
  profile fields stay exactly as they are, including the opt-in profile write.
- **D6. No placeholders for application fields in the message templates**
  (`update_mail_content()` stays as is).

Defaults taken in this document (revisit any of them before its stage starts):

- **D7. Snapshot keys are `a_<fieldid>`**, beside the existing `s_<column>` and
  `c_<profilefieldid>`. The id, not the shortname, because a shortname can be edited.
  The entry shape stays `{key, label, value}`, with the label and the display value
  frozen, so **`SNAPSHOT_VERSION` stays 1**: an older reader (a restore into an older
  site) still renders the entries. A `raw` member may be added beside them;
  `read_snapshot()` already ignores unknown members.
- **D8. Masking: `a_` keys follow the identity rule of profile custom fields**
  (`formatters\submission::visible_keys()`: visible with
  `moodle/site:viewuseridentity`, which teachers, editing teachers and managers hold
  by default). Answers can be as sensitive as a CPF; the conservative rule is the one
  already proven. A per-field "visible to every reviewer" switch is a possible
  follow-up.
- **D9. Types offered in v1: text, select, checkbox, date, number, and pattern.**
  Textarea and picture are left out: both can carry files, which live in the
  `customfield_data` file area this design does not write. Long free text already
  has the comment zone.
- **D10. `uniquevalues` is removed from the handler's field form**; for fields of a
  shared category, where the handler cannot remove it, it is documented as having no
  effect on applications.
- **D11. Display values are frozen in the applicant's language and timezone at
  submission** (a date is `userdate()` then). Checkbox freezes the localised yes/no;
  select freezes the option text; number freezes the field's own formatted output.
- **D12. Conditions belong to the instance, not to the field**, in a table
  `enrol_apply_fieldcond` (enrolid, fieldid, dependsonfieldid, operator, value).
  Field configdata would not work: a shared field's configdata is shared by every
  instance, and `config_form_definition()` never runs for it.
  - v1 operators: on a **select** field, "equals one of these options"; on a
    **checkbox**, "checked" / "not checked". These map to `hideIf` (`in`/`neq`,
    `notchecked`/`checked`) and are re-evaluated server side.
  - A field whose controlling field is hidden is hidden too (chains). Cycles and
    references to a later field are refused at configuration time.
  - A hidden field's required rule is removed at form definition and re-enforced in
    validation only when the field is visible; a hidden field's submitted value is
    discarded, never frozen.
- **D13. Order on the applicant form:** profile fields, then application fields (one
  header per category, core's own headers), then the comment zone.
- **D14. The approver notification lists application answers** with the profile
  fields: it reads the same submitted values, so leaving them out would need extra
  code. This is the existing field list in the e-mail body, not D6's placeholders.
- **D15. Instance lifecycle.** `delete_instance()` deletes the instance's local
  categories (with their fields), its shared-category enablement rows and its
  condition rows. Snapshots are untouched: they outlive the instance by design.
- **D16. Backup and restore carry the definitions with the instance**: local
  categories and fields, enabled shared categories by shortname, and condition rows,
  remapped to the new field ids. A shared category missing on the target site is
  skipped with a restore log line. Restored data is untrusted, like `customtext4`.

## Stages

Each stage is a pull request with its own tests, mutation entries in
`mutations/gates.conf` and a `CHANGELOG.md` entry.

0. **Moodle 5.3 support** (separate pull request, first): `supported` up to 503, the CI
   job, README, mounts, and the suite green on m503.
1. **Handler and configuration page.** `classes/customfield/application_handler.php`
   (`can_configure`, `can_edit` = applicant may apply, `can_view` = reviewer,
   type allowlist, removal of `uniquevalues`, configuration context and URL), the page
   `fields.php?id=<enrolid>` rendering core's management UI, a link from the instance
   settings and the instance actions, cleanup in `delete_instance()`.
2. **Applicant form and freezing.** Fields in `application_form` (D13), validation,
   freezing into the snapshot (D7, D11), the approver e-mail (D14), masking (D8).
   The queue, review page and reports then show the answers with no code of their own;
   tests prove it on each surface.
3. **Backup and restore** of the definitions (D16).
4. **Conditions** (D12): table, upgrade step, configuration section on `fields.php`,
   form wiring, server-side evaluation, privacy note (the table holds no personal
   data). As built, two points differ from D12's wording. A select condition is wired as
   `hideIf(..., 'in', <every other position, 0 included>)`, not `in`/`neq`: core_form's
   dependency manager has no "not in", and several `neq` rules on one element are ORed, so
   any condition naming two options would hide the question always. And the earlier-field
   rule is checked on every read as well as at configuration time
   (`\enrol_apply\local\conditions::effective()`): core's editor can reorder fields, edit
   options or switch a shared category off afterwards, and a condition that no longer holds
   is ignored, so the question is shown rather than lost.
5. **`customfield_pattern`** in its own repository (`moodle-customfield_pattern`):
   the type, the server-side regex check, the AMD mask module, presets, tests;
   then allowed in the handler's allowlist.

6. **Report Builder columns per answer.** The datasource already exists
   (`classes/reportbuilder/datasource/applications.php`: submission, applicant, decider and
   course entities), and after stage 2 its snapshot column carries the application answers.
   That column is one block per application: it cannot be sorted or filtered by a single
   answer. Stage 6 adds that ability. It needs structured storage, because a display callback
   never reaches SQL. Built on D17 and D18.

Stages 3 and 5 are independent of each other and of 4.

### D17 (accepted 2026-10-13). A frozen answer table for reporting

`enrol_apply_answer` (submissionid, fieldkey, label, value, sortorder). It is written in the same
transaction as the application record and with the frozen values, so it obeys D1: it is not a
live copy, and renaming or deleting a field changes nothing in it. It gives:

- **a column and a filter per question in the course report**, for every question the course's
  methods ask, requested profile fields included, masked by D8's rule: a question the reader may
  not see has no column and no filter at all, never a blank one;
- **a column and a filter per field of a shared category** that some method uses, in the custom
  report source. Those fields are the same in every instance, so a site-wide report can have one
  column for "CPF" across all courses;
- **an "Application answer" entity** with one row per answer (field key, question, answer),
  joined to the submission, for the fields that are local to an instance.

In the custom report source all three are withheld, filters and conditions included, from a
reader without `moodle/user:viewalldetails`, the gate the snapshot column already had: a custom
report has no single context to judge D8's rule in. The header of a per-question column is the
question's current name, while each cell holds the value frozen with its record; the frozen
label stays with the record's snapshot.

### D18 (accepted 2026-10-13). The table replaces the JSON envelope

D17 first proposed the table beside `enrol_apply_submission.userinfodata`. It replaces it
instead: the snapshot IS the rows, and the column is gone (upgrade 2026101300).

- **One source of truth.** Privacy export and erasure, pseudonymisation on course deletion, the
  retention sweep and backup each handle one copy of what the applicant typed, not two that must
  stay in step; a second copy is a second place for a deletion to miss.
- **Every reader moved**: the queue (one batch read per page, not one query per row), the review
  page, the privacy export, and the report's snapshot column, now aggregated from the rows in SQL
  (`sql_group_concat`) under its old name, so existing custom reports keep working.
- **The table names no user.** Its owner is the record's applicant, so core's privacy table
  coverage test cannot see it as personal data; the provider declares it and handles it by hand,
  and a decider's erasure never touches it.
- **Old data and old archives still arrive.** The upgrade and the restore of an archive whose
  application record carries the envelope both parse it with the reader it always had (version
  check, every part scalar, malformed entries dropped) and write it through the one writer. The
  upgrade skips a record that already has rows, and each record's rows are written in one
  transaction, so a re-run after a failure is safe. The other direction is lost: an archive
  written now, restored into a site running an older version of this plugin, brings the records
  without their answers, since that version reads only the envelope (D7's forward compatibility
  ends here).
- **What the storage adds.** A key over 100 characters or carrying a control character is
  dropped (shortening or cleaning it could turn a withheld key into a shown one); control
  characters are removed from labels and values (the aggregate separates parts and rows with
  two of them); a key repeated in one snapshot keeps its first value (the table is unique on
  submission and key, because a per-question column reads one value per record). Only a crafted
  archive or envelope reaches any of the three.

## Open questions

- Whether a teacher may also *create* shared categories, or only switch them on, is
  core's call (`shared_handler::can_configure()`); to be checked on a teacher account
  in stage 1.
- The `customfield_pattern` presets list beyond CPF, CNPJ, CEP and phone.
