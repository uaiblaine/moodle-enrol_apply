# enrol_apply — implementation plan: the decision screens

Execution plan for the design approved in
[`applications-desk.html`](applications-desk.html) on 2026-08-31, and for the defects that
investigation turned up. That document is authoritative on *why* and carries the approved mockups;
this one is only about *what to do* and *what will bite*.

Five slices, **U1–U5**. Each stands alone, ships with green CI, and is independently revertable.
Slice 10 of the older plan (the `enrol_apply_submission_field` child table) remains **not
scheduled** by decision, so filters by requested profile field stay out of scope — U5 must not
imply they exist.

Every path below is repo-relative to `/Users/uaiblaine/dev/moodle-enrol_apply`. Core citations are
relative to `/Users/uaiblaine/dev/moodle-502/public` and carry the 5.1 line beside them whenever the
two branches differ.

---

## What was decided, in one table

| Question | Answer |
|---|---|
| What is deferral for? | A **triage state**, not a waiting list. Approved = criteria met, enrolled. Rejected = not met, not queued. **Deferred** = all or part met, waiting either for a *place* or for something to be *validated*. Its own filterable status, with a **reason** field so the trail survives. **No automatic enrolment when places free.** |
| Second apply instance in one course? | **Supported.** New intake, new class, a teacher reusing a course. |
| Two pending applications by one person in one course? | **Allowed.** |
| As-you-type search vs no-JavaScript? | **Search as you type via a web service**, built the way `local_dimensions` builds its own. No-JS keeps paging, sorting and a plain GET search box. |
| Accent-insensitive on PostgreSQL? | **Best-effort** — `unaccent` where present, documented fallback where not. |
| `showuseridentity` context? | Identity columns on the **`?id=` and site-wide scopes only**, never the mentee scope. |
| Prior applications on the review page? | **Yes**, gated on `enrol/apply:viewreports`. |
| Slice 10? | **No.** |
| `info.php`? | **Delete outright**, no redirect stub. |
| Polluted `customtext2`? | **Clean it in an upgrade step**; a CHANGELOG entry is the record. |
| Mockups? | **A** (triage queue) and **C** (rebuilt review page). No details modal — the applicant name is a plain profile link. "Lazy loading" resolves to AJAX paging with a large page size and a real search. |

**One reading recorded explicitly.** "Search as you type, displayed dynamically below" was read as
*the table narrowing*, not a suggestion dropdown, because Mockup A is approved and shows exactly
that, and because a picker narrows to one person and defeats bulk triage. The server side is
identical either way, so a dropdown stays an additive change on the same web service.

---

## Slice map and dependencies

| Slice | What | Depends on | Version bump | Upgrade step |
|---|---|---|---|---|
| ~~**U0**~~ | ~~Re-establish the baseline~~ — **done 2026-09-01** | — | no | no |
| ~~**U1**~~ | ~~Delete `info.php`; finish the custom label~~ — **done**, [#57](https://github.com/uaiblaine/moodle-enrol_apply/pull/57) | U0 | yes | yes (clean `customtext2`) |
| ~~**U1b**~~ | ~~Stop the queue leaking identity, and stop it filtering invisibly~~ — **done**, [#58](https://github.com/uaiblaine/moodle-enrol_apply/pull/58) | U0 | yes | no |
| ~~**U2**~~ | ~~Rebuild the review page as Mockup C~~ — **done**, [#60](https://github.com/uaiblaine/moodle-enrol_apply/pull/60) | — | yes | no |
| **U3** | Deferral as a first-class triage state | — | yes | yes (new column) |
| **U4** | The participants-page bulk menu | — | yes | no |
| **U5a** | Rebuild the queue as Mockup A on `core_table\dynamic`, without the search | U1, U1b, U3 | yes | no |
| **U5b** | The as-you-type search over a plugin web service | U5a | yes | no |

**Order:** ~~U0 → U1 → U1b → U2~~ (done 2026-09-01) → **U3** → U4 → U5a → U5b.

**Why that order.**

- **U0 first, and it is not ceremony.** The handoff records coverage as stale (56.1% from 2026-08-29,
  eight changes since) and `mdl ci --strict` as not re-run since 2026-08-30. Without a fresh baseline
  every later delta is unreadable. Note the trap the handoff already paid for: *every* version bump
  stales both test sites, so `mdl phpunit-init` and `mdl behat-init` must run before the mutation
  sweep — otherwise it reports "reddened nothing" for a suite that never ran.
- **U1b is pulled out of the queue rebuild deliberately.** The e-mail disclosure and the invisible
  initials filter are live defects on a shipped page. Making them wait for the largest slice in the
  plan would be choosing the rewrite over the fix.
- **U3 before U5a**, because "Deferred" on the queue is a filter over a state U3 creates. Building the
  filter first means building it twice.
- **U5 splits in two** so that `db/services.php`, `amd/build/**` and the `grunt --max-lint-warnings 0`
  gate all enter on their own slice. A red matrix leg is then attributable to one change rather than to
  a rebuild plus a web service plus a bundle.
- **U2 and U4 are independent** of everything and may fill any gap.

**Every slice bumps `version.php`** — U4 included, which my first draft had wrong: naming the dropped
applicants needs a lang string, and a lang change bumps here. Three carry an upgrade step: U1 (clean
the polluted `customtext2`), U3 (`db/install.xml` gains a column — bump its `VERSION` attribute to
match the savepoint), and U4 only if the ratchet remedy adds a setting.

**Not in any slice, deliberately:** anything needing the deferred child table, and any change to
`enrol_apply_plugin::is_full()`'s name **or meaning**. Three plugins outside this repo reach it
through `is_callable()`; gate `AC` proves only that it still delegates, and cannot see a change in the
*answer*. So the applicant-cap remedy in U3 is safe only if it changes **data** rather than the
predicate — display-only, or ageing a deferred row out. Making deferred rows stop counting inside
`applicants()` changes the answer for every consumer at once and would need all three PRs in the same
session.

---

## U0 — Re-establish the baseline

No code. Nothing after this is readable without it.

The handoff records coverage as **stale** (56.1% from 2026-08-29, eight changes since) and
`mdl ci --strict` as not re-run since 2026-08-30. Take both numbers again before touching anything,
so every later delta means something.

```sh
mdl ci moodle-enrol_apply --coverage        # single leg only; refused with --matrix
mdl ci moodle-enrol_apply --strict          # phpmd as a real gate
mdl phpunit m501 enrol_apply                # the leg the day-to-day m502 loop never runs
mdl mutate moodle-enrol_apply mutations/gates.conf --dry-run
```

**The trap this repo has already paid for once.** Every `version.php` bump stales *both* test sites.
Run `mdl phpunit-init` and `mdl behat-init` before the mutation sweep, or it reports "reddened
nothing" for a suite that never ran. Judge the sweep by the **test count**, never by the failure
count alone.

---

## U1 — Retire `info.php`, finish the custom label, correct the record

### U1.1 — Fix the gate that is red right now

**This is a live, pre-existing failure and it is not ours to discover twice.** Core's
`core\string_manager_standard_test::test_validate_deprecated_strings_files` asserts
`string_exists()` for every line of every `lang/en/deprecated.txt`
(`lib/tests/string_manager_standard_test.php:134` — the file is **byte-identical on 5.1 and 5.2**,
verified with `diff -q`). This plugin's `deprecated.txt` lists `maxenrolled` and `maxenrolled_help`,
and neither is defined in `lang/en/enrol_apply.php` any more, so the assertion fails on both
branches today.

Core's deprecation contract **keeps the definition**: `string_manager_standard::get_string()`
resolves the string first and only then warns under `debugdeveloper`
(`string_manager_standard.php:391-399`). Deprecating a string here therefore means *listing it and
keeping it*, not deleting it.

- Reinstate `$string['maxenrolled']` and `$string['maxenrolled_help']` in their alphabetical slots
  in **both** packs.
- Nothing in the plugin's own CI sees this: `moodle-plugin-ci` resolves
  `--testsuite enrol_apply_testsuite`, which is `tests/` alone. Invoke it deliberately:

```bash
docker exec m502-webserver-1 php /var/www/html/vendor/bin/phpunit --no-coverage --filter test_validate_deprecated_strings_files core\\string_manager_standard_test
```

### U1.2 — Delete the page

Straight deletion, no redirect stub (decided). Nothing else references the template; `styles.css`
carries no `.enrol_apply-info` rule; `README.md` mentions the page nowhere (measured: zero
occurrences of `info.php`, `info_table`, `Enrol info` or `submitted_info`).

| File | Change |
|---|---|
| `info.php`, `info_table.php`, `templates/info.mustache` | delete |
| `renderer.php:417-432` | delete `info_page()` whole — it is also the "unused parameters" finding: `$manageurl` and `$instance` are never read. Keep `capture_table()`, which `manage_form()` still uses |
| `lib.php:871-875` | delete the `i/files` action icon. It is the page's only inbound link |
| `tests/sort_order_test.php` | drop the `require_once` at `:27`, the `#[CoversClass(\enrol_apply_info_table::class)]` at `:43` and the one-row data provider; the file's other two tests are already queue-only |
| `tests/local/bootstrap_compat_test.php` | remove `info_table.php` from **both** lists — `:150` (guarded by `is_file()`) and `:327` (a bare `file_get_contents()`) |
| `tests/coverage.php:70-71` | remove both entries |
| `tests/behat/behat_enrol_apply.php:56-57` | remove the `application info` resolver arm and its docblock line. No scenario ever used it |
| `lang/{en,pt_br}/enrol_apply.php` | **keep** `submitted_info` defined; add `submitted_info,enrol_apply` to `lang/en/deprecated.txt` — see U1.1 |

**The blocker is in the tests, not the page**, and the design docs name the quieter of the two:
`sort_order_test.php:27` `require_once`s the deleted file, which is a **fatal**;
`bootstrap_compat_test.php:327` is a warning, fatal only under `--fail-on-warning`.

### U1.3 — The custom label, cheap half

- `edit_form.php:131` — delete the dead `setDefault`. It has never fired on any instance here
  (`set_data()` overrides it for any non-NULL scalar, and `lib.php:1009` seeds `''`), and reviving
  it would be worse: saving would freeze the **creator's own language** string into
  `{enrol}.customtext2`, after which the label never follows the language pack again.
- Add `$mform->hideIf('customtext2', 'customint7', 'eq', 0)` and
  `$mform->addHelpButton('customtext2', 'custom_label', 'enrol_apply')`.
- Add `custom_label_help` to both packs in lockstep, in its alphabetical slot. It must say the three
  things the field's history hides: it retitles the applicant's comment box *and* the comment column
  on the queue and the review page; it does nothing unless **Commentary field** is on; and leaving it
  empty uses the shipped wording.

### U1.4 — Thread the label, with the right spelling per sink

**The design document got this wrong for one sink of three, and the correction matters.** It says
every sink renders raw and wants the escaped spelling. Two do; one does not.

| Sink | Renders | Wants |
|---|---|---|
| Queue column header | `flexible_table::print_headers()` → `html_writer::tag('th', $content, …)` (`lib/table/classes/flexible_table.php:1374`, byte-identical on both branches) — content never escaped | **escaped** |
| Application form label | `lib/form/templates/element-template.mustache:50`, `{{{label}}}` | **escaped** |
| Review page comment label | `templates/review.mustache:124`, `{{commentlabel}}` — a **double stash** | **plain** |

Put the switch in one place rather than at three call sites: a new
`classes/local/commentlabel.php` with `custom(stdClass $instance, bool $escape = true): string`,
copying the shape of core's own `field_controller::get_formatted_name(bool $escape = true)`, which
`CLAUDE.md` already names as the pattern. It resolves the context itself as
`context_course::instance($instance->courseid)` and must **not** take the page context — on the
review page that is the applicant's *user* context.

The queue heading carries the label on the `?id=` scope only; the site-wide and mentee scopes span
instances and fall back to the lang string.

### U1.5 — Clean the polluted `customtext2`

**Provenance, read from this repo's own git log rather than reasoned.** Commit `3d27870`
(2016-06-13) made `customtext2` the notification recipient list while the custom label was still
read from it. It was written three ways: the `2016060803` upgrade step (`'$@ALL@$'` or `''`),
`add_instance()`'s defaults (the same two), and `edit.php`'s form save. Alexander Bias's `b88a8d2`
(2022-02-04, *"for fresh installations only"*) moved all three to `customtext3` and **retro-edited
the 2016 step**, so a site already past that savepoint never re-ran it and kept the value.

**Detect narrowly — one literal, not two.** Clean only the exact string `$@ALL@$`. That is the only
value the three legacy writers could produce that is distinguishable from a label; `$@NONE@$` belongs
to `get_users_from_config()`'s vocabulary but no path here ever wrote it into `customtext2`, so
listing it would be a guess dressed as a rule. The comma-separated user-id shape is deliberately left
alone: it cannot be told apart from a label a human might type, and `$@ALL@$` can. Measured on m502:
zero rows match, which is the expected result on a site that never ran the 2016 build.

- `db/upgradelib.php`: `enrol_apply_clear_legacy_comment_labels(): int`, blanking to `''`.
- `db/upgrade.php`: a new final block calling it, ending in `upgrade_plugin_savepoint()`.
- `CHANGELOG.md` must record what the field could have been carrying, the provenance above, and that
  the cleanup is one-way.

### U1.6 — The small fixes and the corrections

- `lang/en/enrol_apply.php:48` — `applydate` reads "Enrol date" and has always meant the date the
  **application** was submitted (the column is `ue.timecreated`). Change the English *value*; keep
  the key, because the meaning does not change. `pt_br` already says "Data da solicitação".
- `confirmusers_desc` promises "gray colored rows" while `styles.css` draws a 3 px left rule. Make
  the words match the CSS.
- Correct the four documented claims that are wrong:
  `implementation-plan.md:1220-1222`, `PROGRESS.md:456-458` and `profile-fields-and-audit.html:2002`
  all say a per-instance Report Builder column title is "inexpressible" because `set_title()` takes
  only a `lang_string`. The type is real (`reportbuilder/classes/local/report/column.php:165`,
  identical on both branches) but it is **not** the obstacle — core's own
  `$string['customfieldcolumn'] = '{$a}'` passthrough carries arbitrary text through it. The real
  obstacle is scope: one entity feeds a site-wide datasource and a course-scoped report, and a
  course may hold two instances with two labels.
- Correct the two `CLAUDE.md` sentences (see U4, which measures both).

---

## U1b — Stop the queue leaking, and stop it filtering invisibly

Pulled out of the queue rebuild on purpose: both are live defects on a shipped page, and making
them wait for the largest slice in the plan would be choosing the rewrite over the fix.

### U1b.1 — Identity fields

The queue prints every applicant's e-mail unconditionally, consulting neither `showuseridentity`
nor `hiddenuserfields`. On m502 `showuseridentity` names only `username` — which is what keeps the
e-mail column off core's participants page — and `hiddenuserfields` lists `email` as well. The queue
beside it prints the address anyway.

- New `classes/local/identity.php` holding the one rule, so the queue and the review page cannot
  disagree.
- `\core_user\fields::get_identity_fields(?context $context, bool $allowcustom = true)`
  (`user/classes/fields.php:363`) for the list, `\core_user\fields::get_display_name()` for each
  heading, and `->get_sql()` (`:485`) for the SELECT and the custom-field joins. The file is
  **byte-identical on 5.1 and 5.2**, so both line numbers hold on both branches.
- Core's own shape to copy: `user/classes/table/participants.php:141-146`.

**Two traps, both measured.**

1. Pass `$namedparams = true`. The `?`/`:name` branch sits inside the profile-field test, so
   standard columns add no parameters at all and the mixed-type throw appears **only once a custom
   profile field is among the identity fields** — which is exactly why a test whose fixture uses
   standard fields alone would pass against the bug. Write the test with a `profile_field_*` entry in
   `showuseridentity`. The queue's existing `for_userpic()` call discards `params` and `joins`
   entirely, so it is **not** a template for this.
2. The custom-field join core emits is an **INNER** join on `{user_info_field}`. A configured
   identity field that no longer exists therefore empties the entire queue rather than dropping a
   column.

**Scope, per the decision:** identity columns on the `?id=` and site-wide scopes only. The `?id=`
scope has one course context; the site-wide scope uses `context_system::instance()`, which is exactly
right for an operator holding the capability at system level and which fails *closed*. The mentee
scope spans courses in one statement, so no single context is right and a per-row mask is unsound
for a sortable column — it gets no identity columns at all.

### U1b.2 — Kill the initials filter, do not merely hide it

Rendering with `out(50, false)` hides the control and nothing else: measured against the real queue,
a stored `i_first = 'Z'` took it from 3 rows to 0 with no bar on the page and nothing to explain it.
`flexible_table::get_sql_where()` reads `prefs['i_first']` / `['i_last']` and never consults
`use_initials`; `table_sql::query_db()` appends the result to both the count and the data SQL.

The complete kill is an override returning `['', []]`. Emptying `$userfullnamecolumns` also works
and silently costs the fullname column its firstname/lastname sub-sort links — do not do that.

---

## U2 — Rebuild the review page as Mockup C

Independent of everything else; may fill any gap.

### U2.1 — Set the course

The review branch calls `require_login()` with no course and never `$PAGE->set_course()`, so
`$COURSE` stays the site course and **the page renders the site front page's secondary navigation** —
eight nodes all pointing at course id 1, one of them `admin/settings.php?section=frontpagesettings`.
It is also why the page has no breadcrumb.

**Three mechanics, all measured, and the order is load-bearing:**

1. `set_course()` must be called **after** `set_context()`. It sets the page context to the course
   context only when none is set, so calling it first would silently replace a mentor's user context.
2. `set_course()` applies **no access check of any kind**. That is what makes it safe for a mentor,
   who holds no course access at all — but it is why the breadcrumb is not free.
3. `set_course()` alone does not reliably produce a course breadcrumb. Build the crumbs explicitly
   with `$PAGE->navbar->add()`.

### U2.2 — The sticky footer

A copy of what `manage_form()` already does: render the bar with `render_from_template`, wrap it in
`new \core\output\sticky_footer($bar, $classes)`, and let the template interpolate
`{{{stickyfooter}}}` **inside** `<form id="enrol_apply_review_form">`.

Five constraints, all verified in the compiled sheets of boost on m501 and m502, boost_union and
boost_union_fundaseg:

- **Only the three buttons.** The bar is a fixed 80 px box whose `.sticky-footer-content` carries
  `overflow: hidden` — about 64 px usable, one row, clipped with no scrollbar. The message textarea
  and both choosers stay in the page body. Note the SCSS is **not** byte-identical across branches
  (5.2 adds rules 5.1 lacks), so check each compiled sheet rather than reading one.
- **One footer per page.** `manage.php` cannot produce two because the review branch exits before the
  queue is built, but the constraint is real (`lib/classes/output/sticky_footer.php:25`).
- **Never call `add_classes()`** — it builds a concatenation and then assigns the argument over it.
  Every class goes through the constructor.
- **Keep `name="formaction"` a form CONTROL**, not the HTML5 attribute: `manage.php:44` reads the
  submitted field.
- **No CSS change is needed.** The plugin's own no-JS polyfill already covers this page: the pagetype
  is `enrol-apply-manage`, which yields body classes `path-enrol` and `path-enrol-apply`, and
  pagelayout `admin` renders through `drawers.php`, whose page div carries `class="drawers"`.

### U2.3 — The destructive decision gets a confirmation step

Confirm is the form's first submit and therefore its **default**, so Enter on a chooser approves the
enrolment. Mockup C's left-to-right order would make Reject the default instead. **Neither default
is safe**, so the fix is not button order alone: intercept `formaction === 'cancel'` without a
`confirmed` flag in `manage.php`'s state-change block and re-render a confirmation page that
re-emits `sesskey`, `userenrolments[]`, `outcomemessage` and `confirmed=1` as hidden inputs.

Core's own precedent for exactly this act is the `$OUTPUT->confirm()` at
`enrol/unenroluser.php:90`, byte-identical on both branches.

**Scope it to the review page, and know why.** `tests/behat/enrol_apply.feature:69-85` posts
`formaction=cancel` **from the queue** and asserts the success message immediately. A confirmation
step that fires on every `formaction === 'cancel'` intercepts that post and the scenario fails on a
page it never expected. Either gate the interception on the review path, or rewrite that scenario —
but decide which, rather than discovering it in CI. And the second POST must itself carry
`formaction=cancel`, as a hidden input or as the submit's own `name`/`value` pair, because
`manage.php` reads that field and nothing else tells it what was confirmed.

### U2.4 — What the page shows

- **One identity gate serving both** the identity line and the snapshot panel, so the two cannot
  disagree. Today the e-mail row is printed unconditionally while the snapshot panel masks identity
  on the course context — one panel withholds what the other prints, to the same reader.

  **Two things make this the hardest decision in U2, and neither is a detail.**

  *The e-mail row would disappear.* Measured on m502, `showuseridentity` names only `username`, so
  `get_identity_fields()` returns **no e-mail for any reader**. Routing the row through it removes it
  outright — and `CLAUDE.md` currently states the opposite invariant, that "the applicant's e-mail
  address has its own row and always did". Decide deliberately between *keep the e-mail row
  unconditional and mask only the snapshot* (today's behaviour, made explicit) and *route everything
  through the identity rule* (consistent, and it costs the e-mail on this site). Whichever is chosen,
  the sentence in `CLAUDE.md` changes with it.

  *An identity line can reintroduce a live profile read.* `showuseridentity` may name **custom profile
  fields**, and the snapshot panel exists precisely because reading the live profile from this page
  was a disclosure defect once already. An identity line built from `get_identity_fields()` reads the
  live profile by construction. That is defensible — it is core's own rule, applied through core's own
  helper — but it must be a stated decision, not a side effect, and it must never be fed by a stored
  snapshot key.
- **The decision columns.** `queue::application()` already LEFT JOINs `{enrol_apply_submission}` and
  selects none of its decision columns. Add `s.id`, `s.status`, `s.timedecided`, `s.decidedby` and
  `s.outcomemessage` — zero extra queries — which is what lets a **deferred** application say who
  deferred it, when, and what they wrote. That is the pairing with U3.
- **Prior applications**, gated on `enrol/apply:viewreports` (decided). Keyed on `(courseid, userid)`,
  which is the table's own `courseuser` index. Reuse the report's own outcome formatter for each row
  rather than the record's bare status — the record says APPROVED after a later suspension.
- **Capacity**, from the four numbers in `capacity`, which must never be mixed: `applicant_limit()` /
  `applicants()` count every non-expired row; `places()` / `places_taken()` count ACTIVE rows only.
- **Drop the duplicate heading** at `renderer.php:304`. Core already renders the `<h1>` from
  `$PAGE->heading`; `core_renderer::heading()` defaults to level 2, which is the `<h2>`.
- **A real page title.** Every review page is titled "Enrol Confirm" today, so every tab and every
  bookmark along the walk is indistinguishable. Name the applicant and the course.
- **A back-to-queue link**, built from the `$scope->url` `manage.php` already computes. One trap: the
  whole `<nav>` currently sits inside `{{#hasneighbours}}`, so a link placed there vanishes on a
  queue of one — widen the wrapper. And the fourth `scope()` branch returns
  `destination::home_page_url()` for an operator who can open no queue at all; that branch must
  render no queue link rather than one that would refuse them.

---

## U3 — Deferral becomes a first-class triage state

### U3.1 — One free-text note, not a coded vocabulary

The decider needs somewhere to record *why*. It must not be `outcomemessage`, which is unambiguously
the **applicant-facing** message: written by `submission::record_outcome_message()`, read by
`submission::outcome_message()`, appended to the mail body by `notify_applicant()`, and exported to
**both** privacy subjects.

So: a new `decisionnote` column — nullable TEXT, after `decidedrole` — present for **all three**
decisions rather than deferral alone, and **free text rather than a two-value enum**.

**Why free text, and why on all three.** Be careful with the first argument, because a sharper
version of it does not hold: the no-JavaScript guarantee does **not** forbid a coded control, it
forbids *conditionally showing or hiding* one. A coded select could sit beside the message box,
ignored by two of the three decisions, exactly as the two choosers already are. What the guarantee
really settles is that the control cannot appear only for Defer — so if it exists it is a control on
every decision, which is an argument for making the note itself universal, not for its type. The type
is settled by the rest: the owner's two scenarios (waiting for a place / waiting for validation) are a
real distinction but a thin one to freeze into a schema; a
coded reason offered as a queue filter would silently under-report — measured, the queue's row set
and the record's diverge in both directions (6 applications awaiting a decision, 1 with no record at
all; 27 records, 21 pointing at a deleted enrolment). If the distinction proves load-bearing in use,
it can be added later as a filterable column beside a note that already exists.

**A live defect the note would otherwise inherit.** `record_outcome_message()` loops over
`get_records(...)` and, when there is no record, writes nothing and says nothing. A decider deciding
`ue=357` on m502 today types a message that is stored nowhere and sent nowhere. The three decision
methods must `ensure()` a record before writing either field.

**Everything the column has to travel through**, and none of it is optional:

| Surface | Change |
|---|---|
| `db/install.xml` | the FIELD, plus bump the file's `VERSION` attribute to match the savepoint |
| `db/upgrade.php` | `field_exists()` guard then `add_field`, ending in `upgrade_plugin_savepoint()` |
| `classes/local/submission.php` | `record_decision_note()` and `decision_note()`, copied in shape from the message pair — and **the writer must clear on empty**, exactly as the message writer was fixed to do, or a re-queued application inherits the last decision's note |
| `lib.php` | widen `wait_enrolment()` and `cancel_enrolment()` to the same `(array, string, ?array)` triple `confirm_enrolment()` already takes; write the note **before** the enrolment is mutated |
| pseudonymisation | blank it on course deletion, like the other free text |
| `backup/moodle2/*` | add to the `submission` element; no annotation — it holds no ids |
| restore | `$data->decisionnote ?? ''` — and the `??` is the **ordinary** path, not padding: an empty backup element parses back as NULL |
| `classes/privacy/provider.php` | declare in `get_metadata()` and export for **both** roles |
| Report Builder entity | a column and a `text` filter, modelled on the existing `comment` pair |
| `templates/decision_controls.mustache` | a second textarea, plus its `Example context (json)` entries — the mustache lint renders them |

`db/install.xml` gaining a column means core's privacy table-coverage test must be invoked
deliberately; it is **not** in this plugin's testsuite. The command is in `CLAUDE.md`.

### U3.2 — Tell the applicant

`enrol_page_hook()` branches only on *does a row exist*, so a deferred applicant reads the pending
message verbatim. Split it three ways, and **move the applicant's own row to the first test** — today
`allow_apply()` is tested first, so the moment the method stops accepting applications, somebody who
already applied is told "Enrolment is disabled or inactive", which is a message about somebody else's
problem. `applied.php:40` has the identical defect and takes the identical fix, keeping its
`false` arm, which is the whole disclosure gate.

### U3.3 — Give the notifications a working default

All six subject and body settings ship as `''`, and e-mail is on by default for every provider. On
m502, **14 of 14** applicant-facing notifications ever sent have an empty subject and 10 of 14 an
empty body — while the 34 sent to *managers* all have both, because that one is built from the
plugin's own template rather than from an empty admin setting.

**The fallback must be at read time, in `notify_applicant()`.** A `settings.php` default cannot reach
any existing site: `admin_apply_default_settings(NULL, true)` runs only from `install_core()`, and its
recursive pass skips any setting whose `get_setting()` is not null — and m502 already stores these.

Two traps to respect while doing it:

- **The subject and the body want opposite spellings.** `update_mail_content()` escapes with `s()`
  and `format_string()` because its result lands in `fullmessagehtml`; the subject is not HTML and is
  escaped again by the sink.
- **Every applicant notification is composed in the *decider's* language**, including the
  `smallmessage` the Moodle app shows. Fixing that is a separate, larger change — record it, do not
  quietly half-fix it here.

### U3.4 — Stop reporting a no-op as success

Deferring an already-deferred application changes nothing and reports "Applications updated".
Two halves:

- Widen `wait_enrolment()`'s lookup from `['id' => $enrol, 'status' => ENROL_USER_SUSPENDED]` to
  `get_pending_user_enrolment()`, which admits SUSPENDED and WAIT alike exactly as confirm and cancel
  already do. That is also what lets a decider **edit the reason** on an application already
  deferred, which the owner's model requires.
- Count by re-reading and report the unchanged rows, the way `classes/bulk/` already does.

### U3.5 — The ratchet

A deferred row counts against **Maximum applicants** for ever. All four legs verified independently:
`applicants()` has no status clause; `wait_enrolment()` writes `timeend = 0` so no arm of
`process_expirations()` can reach the row; nothing but `wait_enrolment()` ever writes
`ENROL_APPLY_USER_WAIT`; and `get_unenrolself_link()` demands `ENROL_USER_ACTIVE`
(`lib/enrollib.php:2517`, identical on both branches) so the applicant cannot withdraw. Measured on
instance 4: one deferred row, limit 1, `applications_closed()` true, `places_taken()` 0.

**Remedy: make it visible, and make it cancellable in two clicks.** A new
`capacity::deferred(stdClass $instance): int` — which needs a `require_once` of the plugin's
`lib.php` at the top of the file, because `ENROL_APPLY_USER_WAIT` is defined there, `lib.php` is not
autoloaded, and `classes/local/capacity.php` is. Without it the method is a **fatal on first call**,
not a wrong answer. The report formatter and the bulk operation already carry that `require_once` for
the same reason. Write the query out in full with its own parameter array —
the class's own docblock records that `fix_sql_params()` tolerates surplus named parameters, so a
shared array runs clean and reddens nothing. Surface it in the queue's capacity header beside the
other numbers, and pair it with U5's **Deferred** filter, which together turn an unexplainable
ratchet into a two-click bulk cancel.

**Two remedies rejected, and one deferred to the owner.**

- *Excluding deferred rows from `applicants()`* — rejected. It changes what `is_full()` **answers**
  for `local_dimensions`, `local_unlistedcourses` and `theme_boost_union_fundaseg` at once, all of
  which reach it through `is_callable()`. Gate `AC` proves only that the method still delegates; it
  cannot see a change in the answer.
- *A separate cap for deferred rows* — rejected as a setting nobody asked for.
- *An applicant withdraw control* — this is the real fix, and it needs a decision the owner has not
  taken: whether an applicant may withdraw at all, and what that does to the cap. Recorded, not built.

### U3.6 — Vocabulary

Re-word the waiting-list strings without renaming a single key: the referent is unchanged — status 2
is still status 2 — and by this repo's own rule a key is renamed only when its **meaning** changes.
That is the `maxenrolledreached` precedent, not the `maxenrolled` one.

**One collision to keep in mind while doing it:** the enrolment vocabulary and the record vocabulary
collide on **0, 1 and 2** — not only on 2 — and at 0 and 1 the same integer means opposite things
(`ENROL_USER_ACTIVE` is 0 while `STATUS_PENDING` is 0). The queue's filter is over the **enrolment**
vocabulary, because its main table is `{user_enrolments}`.

---

## U4 — The participants-page bulk menu tells the truth

The four core files this slice reads — `user/index.php`, `user/action_redir.php`,
`enrol/locallib.php` and `lib/classes/output/html_writer.php` — are **byte-identical on 5.1 and 5.2**
(`diff -q` clean), so no line number below needs a branch qualifier. Do not generalise that beyond
these four: `lib/pear/HTML/QuickForm/static.php`, which the confirmation form renders through, is
**not** identical (`:137-140` on 5.1, `:127-130` on 5.2).

### U4.1 — Collapse the duplicate optgroups

`user/index.php:257` calls `get_bulk_operations($manager)` once per **instance**, on the same plugin
object every time (measured: one object, both iterations), with a manager carrying no instance
filter. The url it builds is `['plugin' => …, 'operation' => …]` and nothing else, so *N* instances
give *N* byte-identical optgroups. Reproduced in stock core: three added `enrol_self` instances gave
**four** Self enrolment optgroups — four, because a disabled instance produces one exactly like an
enabled one.

**The memo's predicate is `empty($manager->get_enrolment_filter())`**, and it is an exact
discriminator, measured across all three callers:

| Caller | Manager | Offers the menu? |
|---|---|---|
| `user/index.php:257` | unfiltered (`get_enrolment_filter()` returns `NULL`) | yes — memo applies |
| `user/action_redir.php:197` | filtered (returns `'4'`) | yes, always — this is the dispatch |
| `enrol/renderer.php:387` | gated on `get_filtered_enrolment_plugin()`, which is false for an empty filter | always filtered |

**Use an instance property, never a static.** `enrol_get_plugins()` constructs a fresh plugin object
on every call, so a static memo would outlive the manager it belongs to and survive across PHPUnit
tests. Two further traps: `get_enrolment_filter()` returns `null`, the integer `0`, *or* the id as a
**string**, so never test it with `=== null`; and an existing test — the second
`get_bulk_operations()` call in `test_the_bulk_menu_is_empty_without_the_capability` — is already a
live control against a memo that ignores the filter. Do not "simplify" it away.

### U4.2 — The silent partial decision, which is now the primary defect

Because one person may legitimately hold two pending applications in one course (decided),
this is the reachable case rather than the edge one. `action_redir.php` filters the manager to the
**first** apply instance, so `get_users_enrolments()` returns only that instance's row. Measured: the
user comes back carrying only the instance-4 row, `$removed` is empty, core warns **nothing**, and
the plugin reports "decided: 1".

**Warn; never decide the other instances.** The reasoning has to be recorded, because deciding them
is the intuitive fix:

1. Under the owner's own model two instances are two intakes. Approving IMA 1 is a statement about
   IMA 1, not about IMA 2.
2. The decision carries **per-instance** data: the role fallback is `$instance->roleid`, the groups
   come from that instance's `enrol_apply_groups`, and the caps `customint3`/`customint4` are its
   own. None of it is meaningful on the other intake.
3. A deferral note written about intake A is a falsehood attached to intake B.
4. It exceeds the scope core's dispatch handed the plugin.
5. In the different-applicants case core has already warned, so the silent case is the narrow one.
6. A warning is reversible by the operator; a decision is not.

*Not* a reason, though it is the intuitive one: "the operator was never asked, because the option url
carries only the plugin and the operation". The url cannot carry the instance — but the confirmation
form can carry anything, so that argument does not survive. The case rests on 1–6.

Implementation: a new `queue::other_applications(int $courseid, array $userids, array $excludeueids)`
built exactly like `queue::neighbour()` — starting from `awaiting_decision_where()` so it inherits
gates C and D — and a **fourth counter** in `decision_operation::process()`, read **after** the
decision. Say the same thing **before** it too, on the confirmation form, which is the only surface
in this flow that can speak before anything is written.

`get_in_or_equal()` throws on an empty array and this query takes two id lists, either of which can
be empty. Guard both.

### U4.3 — The disabled-instance capture

`enrol_get_instances($courseid, false)` returns disabled rows ordered by `sortorder`, so a **disabled**
apply instance sorting first captures the entire dispatch: `get_users_enrolments()` returns 0 rows and
core redirects with "No users selected". Measured on both branches, and far more reachable than two
enabled instances — disabling rather than deleting an old method is ordinary practice, and sortorder is
teacher-editable from `enrol/instances.php`.

Add `dispatch_instance(int $courseid): ?stdClass` to the bulk base class, reproducing
`action_redir.php:176-183` **literally** (it must be `enrol_get_instances($courseid, false)`, not the
enabled-only call and not the manager's own).

**Do not gate the menu on that instance's `status`.** It is the intuitive fix and it is the wrong
test: an *enabled* instance that simply is not the one the selection lives on breaks the dispatch in
exactly the same way, so a status gate closes one door and leaves the identical one open beside it.
The disabled case is not a separate defect — it is U4.2's defect reached by a different route, and
U4.2's warning is what covers both. Refusing the menu outright would also cost a real route: a course
whose only apply instance is disabled can still hold pending applications, and the dispatch serves
them correctly today.

What `dispatch_instance()` is for, then, is **naming the instance in the warning** — telling the
operator which method was actually decided — not refusing to render the menu.

### U4.4 — The disabled plugin

The menu is built from the **all-plugins** list (`user/index.php:250` passes `false`, meaning *include
disabled*) while `action_redir.php` uses the **enabled-only** list and throws
`errorwithbulkoperation`. So a site-disabled plugin's entries are offered and then throw. Add
`enrol_is_enabled($this->get_name())` as the first gate.

`CLAUDE.md` blames the wrong file for this; correct it in the same change.

### U4.5 — The two wrong sentences

- **"applicants of the second are dropped by core with a per-user warning"** — true when the selected
  people are *different*, false for one person holding both, where `$removed` is empty, core warns
  nothing and the plugin reports success. That sentence argues the next reader out of the only silent
  case.
- **"the bulk menu … builds it from the enabled-only list"** — the opposite of the truth, and it names
  the wrong file. See U4.4.

### U4.6 — Coupling to U3

Bulk deferral must offer the new note, or it writes a reason-less triage record while the queue's
deferral writes one. `decision_operation` already has the hook shape — `offers_decision_controls()`
returns false in the base and true only in `confirm_operation`, passed through `get_form()` as
`$customdata`. Add a second flag the same way, true only in `wait_operation`.

### U4.7 — Upstream

Worth one tracker issue: the duplication reproduces in **stock core** with `enrol_self` and no
third-party plugin, so the fix belongs in `user/index.php`. Component Enrolments/Participants,
affects both stable branches.

---

## U5a — Rebuild the queue as Mockup A, without the search

### U5a.1 — The surface

Replace the root-level `enrol_apply_manage_table extends table_sql` with an autoloaded
`\enrol_apply\table\applications extends table_sql implements \core_table\dynamic`, plus
`\enrol_apply\table\applications_filterset`.

Core resolves the handler generically as `\{$component}\table\{$handler}`
(`lib/table/classes/external/dynamic/get.php:202` — the whole file is byte-identical on 5.1, verified
with `diff -q`), and `enrol_apply` cleans through `PARAM_COMPONENT` (measured). **No plugin type in core implements this interface** — the only
implementors are `admin`, `ai`, `reportbuilder`, `sms` and `user`. The generic resolution means a
plugin can; nobody has.

Follow `core_sms\table\sms_gateway_table:45-54` and `core_admin\table\plugin_management_table:51`:
the constructor takes **no** argument and pins the uniqueid server-side. `get.php` calls
`new $tableclass($uniqueid)` and PHP silently ignores the extra argument.

### U5a.2 — The scope, which is the largest correctness risk in the plan

`get.php` builds the table, then does exactly three things: `set_filterset($filterset)`,
`validate_context($instance->get_context())`, `has_capability()`. **It never calls
`filterset::check_validity()`.** So the scope arrives from the client, and there is one capability
check against one context — while this queue has three scopes across two context levels.

**The resolution:** a single **required** `enrolid` integer filter (0 = no instance), and everything
else — the course, the context, the mentee id list, the capability — recomputed server-side from it
on every request, through one new `queue::listing_scope(int $enrolid)` that `manage.php` uses as well.

- The mentee restriction **never travels in the filterset**, so a forged request cannot widen it.
- A forged `enrolid` is refused by the course capability check, exactly as `manage.php?id=` refuses
  it today.
- `enrolid` is *required* so that an omitted scope is a hard error rather than a silent choice — but
  **`set_filterset()` must call `check_validity()` itself**, because `get.php` never does, so
  "required" is otherwise a claim nothing enforces.
- **Filter names must be strictly alphanumeric.** The service declares the name as `PARAM_ALPHANUM`,
  and `validate_parameters()` throws `invalid_parameter_exception` on `user_search` or
  `first-initial` (measured). No underscores, no hyphens, ever.
- **`integer_filter::add_filter_value()` requires a real `int`.** The service declares filter values
  as `PARAM_RAW`, which preserves the JSON type verbatim — measured, `7` arrives as an integer, `"8"`
  as a string. Everything the AMD module reads out of `dataset` is a **string**, so it must be
  `Number()`-cast before it enters a filter or the request dies with a TypeError.
- **Keep `UNIQUEID = 'enrol_apply_manage_table'`.** It is what stored sort preferences are keyed on,
  so changing it silently discards every operator's saved sort.
- One thing the dynamic path gets for free and should not re-implement:
  `external_api::validate_context()` calls `require_login($course, …)`, which is the same
  course-access test `manage.php` applies today.

### U5a.3 — The rest of the rebuild

- **Kill the initials filter on both paths** (U1b.2 does the SQL half; the dynamic path re-arms it as
  a data attribute, so `initialbars(false)` is needed too).
- **Identity columns** from U1b.1, scoped as decided.
- **Capacity header** from the four numbers plus U3's `deferred()`.
- **Per-row Review link** to `manage.php?userenrol=`. The queue builds no such link today — its only
  doors are the participants icon, the notification e-mail and the previous/next chain — so this is a
  new door, not a restyled one.
- **The applicant name is a plain `user/view.php?id=&course=` link.** No details modal (decided).
- **Responsive cards without abandoning `table_sql`:** `flexible_table::set_columnsattributes()` puts
  arbitrary `data-*` on every cell of a column, keyed by the **column name** (the docblock's
  `'c0_firstname'` example is misleading; the code uses `$colbyindex[$index]`). Below the breakpoint
  CSS turns each `<tr>` into a card and reads the label out of the attribute.
- **The bulk bar must not lie.** `refreshTableContent()` replaces the entire region node, so a page
  turn, a sort, a filter change or a page-size change destroys every checkbox in it. The bar lives in
  the sticky footer *outside* that region, so it survives with a stale enabled state unless reset.
  Its label says **"N selected on this page"**, and the module resets the toggler state after every
  refresh.
- **Delete `manage_table.php`** and update every reference. The list is longer than a grep for the
  class name suggests, and three entries are gates rather than call sites:
  `tests/coverage.php` names `manage_table.php` in `$includelistfiles` (a stale path there breaks
  `mdl ci --coverage`, which the handoff records as having failed outright before);
  `tests/local/bootstrap_compat_test.php:327` reads it with an **unguarded** `file_get_contents()`,
  so it goes red exactly as it does for `info_table.php`; and `tests/sort_order_test.php:52` pins the
  sortable columns as `['course', 'fullname', 'email', 'applydate']`, which folding identity into the
  name column changes. `tests/local/queue_test.php`'s `listed()` helper constructs the table directly
  and needs a structural rewrite, not a rename. `TOGGLE_GROUP` moves to the new class unchanged.

### U5a.4 — Four things that would break it, and are not obvious

**1. Escaping. This is the largest hazard in the slice.** `flexible_table::format_row()` writes
`$row->$column` into the cell with **no escaping at all**. Core's own participants table closes
exactly this by returning `s($data->{$colname})` from `other_cols()`. Identity fields are
user-controlled strings, so adding them as columns without that is an XSS hole in a page only
managers see — which makes it quieter, not smaller. Copy core's `other_cols()` shape.

**2. The whole feature is dead on an empty queue.** `renderer.php` gates `js_call_amd()` on
`hasrows`, and `templates/manage.mustache` gates *both* the decision controls and the sticky footer
on it too. A search box that vanishes the moment a search matches nothing is unusable — and an empty
queue is a state this plugin already reasons about elsewhere (the places notice is rendered outside
that section for precisely this reason). The filter bar, the search and the capacity header must all
render outside `hasrows`; only the bulk bar belongs inside it.

**3. A refreshed table carries no JavaScript.** `get.php` returns `['html' => …, 'warnings' => []]`
and — unlike `core_form\external\dynamic_form` — never returns `get_end_code()`. So anything a
refreshed cell needs must be delegated from a stable ancestor or re-initialised by the caller after
every refresh. This is the same fact that forces the bulk bar's state reset.

**4. The no-JavaScript search form cannot live inside the decision form.** `manage.mustache` is a
single `<form method="post">` wrapping the notice, the table, the controls and the sticky footer, and
HTML forbids nested forms. The GET search must sit **outside** it — above the POST form — with
`guess_base_url()` carrying its value so paging keeps it.

### U5a.5 — The interface contract, precisely

`interface dynamic` declares exactly one method: `has_capability(): bool`. The rest is not on the
interface and is easy to get wrong:

- `get_context()` is mandatory because `get.php:230` calls it — not because the interface says so.
- `guess_base_url()` is mandatory, and `set_filterset()` calls it, so it must carry **every**
  GET-encoded filter — `status` included — or the no-JavaScript path silently loses it.
- `set_filterset()` itself is optional.
- `has_capability(string, context, …)` requires a real context. Returning `false` from a
  scope resolver and passing it through is a **TypeError**, not a refusal.

The three lines that matter in `get.php` are `:202` (the class resolution), `:228-231` (build,
set filterset, validate context, check capability) and `:237-243` (the initials re-application) — the
whole file is byte-identical on 5.1.

### U5a.6 — What degrades and what does not

Paging and sorting emit **real anchors**, so they work with JavaScript off. **Filters do not** —
`setFilters` exists only in JavaScript and there is no GET encoding of a filterset anywhere. So the
search box must **also** be a plain `<form method="get">` whose value `manage.php` reads into the
filterset server-side, and `guess_base_url()` must carry it so paging keeps it.

---

## U5b — The as-you-type search

**No plugin web service is needed, and the first draft of this plan had that wrong.** The dynamic
table already has one: `core_table_get_dynamic_table_content`, registered `ajax => true` at
`lib/db/services.php:2960` on 5.2 and `:2954` on 5.1 (verified on both). The search is a debounced
`input` handler calling `setFilters()`. So `db/services.php` is not created and this slice exists for
two other reasons: the **AMD/grunt gate** and the **PostgreSQL `unaccent` question**.

- **The debounce shape to copy** is `local_dimensions/central/participants_users.js:236-244` —
  `addEventListener('input', …)`, `window.clearTimeout`, `window.setTimeout(…, 250)`, then reload.
  (The `core/form-autocomplete` + datasource-module shape in `user_datasource.js` is the *picker*
  pattern; it is the right one for choosing a user and the wrong one for filtering a queue.)
- **Accent-insensitivity** copies the shape of `local_dimensions\helper`: `has_unaccent()` (asks
  `pg_extension` on **every** call rather than caching, because PostgreSQL PHPUnit rolls each test
  back and a cached flag goes stale), `ensure_unaccent()` (`CREATE EXTENSION IF NOT EXISTS` in a
  try/catch, called from **install/upgrade only**, never per keystroke), and `sql_like_ai()` falling
  back to `$DB->sql_like($f, $p, false, false)`.
- **Two behaviours in the field, and say so.** MariaDB is accent-insensitive by collation; PostgreSQL
  is not, and core says so itself. Best-effort was decided; the CHANGELOG and the admin-facing help
  must record which sites get which.
- **`unaccent` exists on m502 only because `local_dimensions` installed it.** The CI PostgreSQL image
  has the files and no created extension. A test *can* provision it for itself:
  the `CREATE EXTENSION` is visible for the rest of the test and rolled back after, which is what
  `local_dimensions`'s own test does, provisioning and skipping only if that fails.

Gates this slice adds that no earlier slice touches: `grunt --max-lint-warnings 0` (eslint
`promise/always-return`, `camelcase` on payload keys — quote them, `max-len` 132) and the
`amd/build/**` rule that the rebuilt bundle ships in the **same commit** as its source, with a
version bump so the cache revision changes.

---

## Cross-cutting rules

These apply to every slice, and each one is here because a verification pass caught it missing.

### Ordering inside a slice

Three pairs must land in the **same commit**, because the failure mode of splitting them is silent
rather than loud:

- **U1** — the `addHelpButton` and its `custom_label_help` string. `help_icon::export_for_template()`
  throws when the string is absent, so a half-landed pair takes the instance edit form down.
- **U3** — widening `wait_enrolment()`/`cancel_enrolment()` and the caller that passes the note. PHP
  **silently ignores surplus arguments** to a userland function, so a caller landing first drops the
  note with no error anywhere.
- **U5b** — `amd/src` and its rebuilt `amd/build` bundle, plus the version bump.

### Every guard gets a mutation gate, in the same commit as the guard

`mutations/gates.conf` has 35 tagged lines today, ending at `AI`, and `mutations/README.md` documents
about 4 minutes per line — so the current sweep is roughly 140 minutes and each slice's additions
extend it. New guards this plan creates, each of which needs a gate: the identity scope (U1b), the
`get_sql_where()` kill (U1b), the `set_course()` call (U2), the confirmation interception (U2), the
note's clear-on-empty writer (U3), the `ensure()` call (U3), the memo predicate (U4), the
other-instances warning (U4), and the filterset's server-side re-authorisation (U5a).

Two rules the file's own README already carries and which bite here specifically: perl interpolates
`$variables` on **both** sides of `s///` and `\Q…\E` does not stop it, so every `$` is escaped; and
after U4, `lib.php` carries the same
`has_capability('enrol/apply:manageapplications', $manager->get_context())` line in **two** methods,
so every pattern must be anchored and `--dry-run` must confirm it matched exactly one line.

**A mutation that reddens nothing is a finding, not a formality** — and check the test *count*, not
the failure count, because a staled test site reports zero failures having run zero tests.

### Every new template carries an `Example context (json)` block

The mustache lint renders each template against it and validates the HTML. U2 adds
`review_actions.mustache` and a confirmation template, U3 widens `decision_controls.mustache`, U5a
rewrites `manage.mustache` and `manage_actions.mustache`. Supply **non-empty** loop data, and never
write a `{{…}}` tag inside the `{{! … }}` docblock — Mustache comments close at the first `}}`.

### Tests are part of the slice, not after it

No step above is done when the production file changes. At minimum:

| Slice | Test work |
|---|---|
| U1 | rewrite `sort_order_test.php` as queue-only; drop `info_table.php` from both lists in `bootstrap_compat_test.php` and from `tests/coverage.php`; **add the first assertion anywhere on the queue's comment column header** — measured, there is none today, so the escaping split this slice introduces would be held by nothing |
| U1b | an identity test whose fixture puts a **`profile_field_*` entry** in `showuseridentity`, because standard fields alone never reach the branch that throws; a test that a stored `i_first` no longer filters |
| U2 | `renderer_test.php`'s e-mail row and masked-reader tests; a test that the review page's course is the application's course, not the site |
| U3 | privacy provider tests for both roles; backup/restore round trip; **and re-run core's privacy table-coverage test by hand** — it is not in this plugin's testsuite |
| U4 | a two-instance fixture (the existing `selection()` helper is single-instance by construction); the existing second `get_bulk_operations()` call is already a control against a memo that ignores the filter |
| U5a | every test that constructs the table needs a structural rewrite, not a rename; `queue_test.php`'s `listed()` helper builds it directly |

### Behat

Two scenarios drive the queue's bar and one drives the review page. The non-JavaScript cancel
scenario is the one that proves the queue still works without JavaScript — **it is the guarantee, so
do not make it `@javascript` to get it passing**. Read the lang string before writing an assertion,
and remember `I should see` is an XPath `contains()` with no whitespace normalisation, so a label and
its value must stay on one source line.

**A version bump stales the Behat site**, and an outdated site prints "Your behat test site is
outdated" and exits **0 with zero scenarios run**. Judge every Behat run by its scenario count, never
by its exit status.

### CI

`mdl ci <repo> --matrix` before every push — the no-flag form is one leg (5.1 / PHP 8.3 / pgsql) and
proves nothing about the others. Read the per-leg logs, not the summary line, which has contradicted
its own detail. The gates most likely to bite, by slice: `phpcs` on every new file (the blank line
after `class X {` has been reintroduced three times in this repo); `phpdoc --max-warnings 0` on the
test files U1 rewrites, which lose parameters when their data providers go; `validate` on the 5.02
leg for any new combined member modifier in a file it parses; the mustache lint on every new
template; and `grunt --max-lint-warnings 0` on U5b alone.

### Documentation that must move with the code

- `CLAUDE.md`: the two wrong sentences (U4.5), the file-layout line that still names
  `templates/info.mustache`, and the e-mail-row invariant if U2 changes it.
- `docs/design/PROGRESS.md`: the `info.php` reconsideration paragraph and the "wait for slice 8"
  section, plus the Report Builder title claim.
- `docs/design/implementation-plan.md` and `profile-fields-and-audit.html`: the same title claim.
- `CHANGELOG.md`: an entry per slice, and U1's must record what `customtext2` could have been
  carrying, the provenance, and that the cleanup is one-way.

---

## What this plan does not do

Recorded so the next reader does not take silence for an oversight.

- **Per-field filters on the requested profile fields.** Blocked on the child table, which is not
  scheduled (decided). The queue must not imply they exist.
- **A details modal on the queue.** Not needed (decided); the applicant name is a plain profile link.
- **Automatic promotion from the waiting list.** Explicitly not wanted (decided) — and it would be
  the first thing in this plugin to enrol somebody without a human decision.
- **An applicant withdraw control.** The real fix for the applicant-cap ratchet, and it needs a
  product decision that has not been taken: whether an applicant may withdraw at all, and what that
  does to the cap.
- **Notifications in the applicant's language.** Two of the three are composed in the decider's
  language and the third in the site default. The remedy is one `force_current_language()` wrapper,
  but it changes every applicant-facing message at once and belongs in its own change.
- **The upstream `user/index.php` fix.** Worth a tracker issue (U4.7); not worth waiting for.
