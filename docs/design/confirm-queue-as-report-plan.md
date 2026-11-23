# Confirm enrolments on the data report: plan

The approval queue (`manage.php?id=<method>`) gains what the course report has (a bounded grid with one
column per answer, a Columns menu with sets, a filter panel with counts, a card view, a pager) and keeps
what only the queue has (the selection, the decisions, the capacity tiles, the Deferred scope), plus the
profile picture and the tags. The approved picture is `confirm-queue-as-report-mockup.html`. Decided by the
owner on 2026-11-23: the new page is for Moodle 5.3 only, and the plugin itself is Moodle 5.3.1+ from this
change on (default branch `MOODLE_503_STABLE`, `frozen/moodle-501-502` for the older lines).

Decisions are numbered CQ-D. The owner took the recommended answer of every open one on 2026-11-23, and asked
for stages 0 to 5 in one line of work.

## 1. What the survey of the code settles

- **The queue is not "the report filtered to awaiting".** The report lists `enrol_apply_submission` records
  of one course to holders of `enrol/apply:viewreports` (managers by default). The queue lists
  `user_enrolments` awaiting a decision, read from the live enrolment, to holders of
  `enrol/apply:manageapplications` or `viewapplications` (editing teachers too), in three scopes: one
  method, the whole site, the reader's mentees. An application with no record is in the queue and not in the
  report; a deferred one is open in the queue and decided in the report's first-response measure; a mentee's
  application is reached through a user context no course report knows. So the queue keeps its own rows,
  its own scope and its own capability, and borrows the report's presentation and its headless pattern, not
  its report class. (CQ-D1, settled by the code.)
- **The queue already has the hard parts on the server**: `\enrol_apply\table\applications::build_sql()`
  with `scope_where()` (awaiting, method, mentees, group narrowing), the per-page batch reads (answers,
  profile links, group visibility), the masking of answers by `submission::visible_keys()`, the identity
  fields through core, the filters (`queuefilter`, `groupfilter`, `coursefilter`, search) and the counts
  behind Pending and Deferred. What it lacks is a headless door: it only renders HTML.
- **No web service decides anything.** Every decision is a POST to `manage.php` (`formaction`,
  `userenrolments[]`, `outcomemessage`, `decisionnote`, `groups[]`, `roleid`, `sesskey`) handled by
  `decision::apply()`, with every guard inside the three decision methods.
- **The React client is the report's own**: `Bar`, `Grid`, `Cards` and `Band` assume the report's row
  shape, scope vocabulary and download; `Segmented`, `Pager`, the icons, the pending holds and most of
  `cells` and `filters` are generic. The bundle has one entry today.
- **One defect found on the way**: the queue's join to the record has no "newest record" restriction, so an
  enrolment with two records would be listed twice. Whether data produces that case is not established; the
  headless read must not inherit it.

## 2. Decisions

| Id | Decision | State |
|---|---|---|
| CQ-D1 | The queue keeps its own engine (enrolments, scopes, capability); it does not become a scope of the report | settled |
| CQ-D2 | The plugin is Moodle 5.3.1+ only: `requires = 2026100501`, `supported = [503, 503]`; 5.1 and 5.2 live on `frozen/moodle-501-502` | owner, 2026-11-23 |
| CQ-D3 | The new page replaces the queue of ONE method (`?id=`) first; the site-wide and mentee queues keep today's server-rendered table until stage 6 | owner, 2026-11-23 |
| CQ-D4 | Decisions stay a form POST to `manage.php` with today's contract, sent by the client; no decision web service | owner, 2026-11-23: no second copy of the guards, the redirect and its messages stay as they are |
| CQ-D5 | Without JavaScript the page shows today's table (in a `noscript`, as the report keeps core's table), and `?view=table` asks for it; nothing of today's queue is deleted in this work | owner, 2026-11-23 |
| CQ-D6 | Filters live in the page's address, as today (a link to a filtered queue keeps working, and the band of the report already links here with `status=`); they are not stored per user as the report's are | owner, 2026-11-23 |
| CQ-D7 | The grid's column choice is a user preference of its own (`enrol_apply_queue_grid`), with three sets: everything asked, decision at a glance, answers | owner, 2026-11-23 |
| CQ-D8 | "Earlier applications" is a column only for a holder of `enrol/apply:viewreports`, as the "Applied before" label is today | settled by the existing rule |
| CQ-D9 | Page sizes 30, 50, 100 as the report; the default stays 50 (MQ-D13) | owner, 2026-11-23 |
| CQ-D10 | The top tiles are the queue's capacity (places, applications held, awaiting with the oldest wait, open or closed); the report's turnaround band is not brought | settled by the mockup |
| CQ-D11 | No download on the queue; "Open the report" for a holder of `viewreports` | settled by the mockup |

## 3. Stages

Each stage is one pull request against `MOODLE_503_STABLE`, with its tier and gate.

### Stage 0: the plugin becomes 5.3.1+ only (tier 3)

- `version.php`: `requires = 2026100501`, `supported = [503, 503]`, a version bump.
- `.github/workflows/ci.yml`: the 5.2 and 5.1 jobs go; the push trigger names `MOODLE_503_STABLE`.
- `README.md`, `CHANGELOG.md`, the plugin's `CLAUDE.md`: the supported range, the branch model.
- Code that exists only for 5.1 and 5.2, listed before removing any of it: `report_view::wanted()`'s branch
  test and core's table as the report's page below 5.3; test metadata written as docblocks instead of
  attributes; the 5.3-only test skips; any `$CFG->branch` read. Removed where a test proves the 5.3 path,
  kept where the frozen line is not the reason.
- The three informing notes as info alerts (commit `23bb463`, already gated) ride here.
- moodle-dev: `plugins.conf` mounts a second checkout, `~/dev/moodle-enrol_apply-frozen` on
  `frozen/moodle-501-502`, in m501, m502 and m502b; `mdl mounts`, `mdl up` on those stacks, agreed with the
  sessions using them. The plugins that reach `enrol_apply_plugin::is_full()` through `is_callable()`
  (local_dimensions, local_unlistedcourses) keep working against either line.
- Gate: every 5.3 leg (PHP x database) with Behat; the mutation sweep of the gates the diff touches.

### Stage 1: a headless queue (tier 3: SQL, web services)

- `\enrol_apply\local\queueengine`: the one reader of the queue for a client. It builds the existing
  `applications` table for the scope `queue::listing_scope()` gives (never from the request), and returns a
  page of rows, the total, the scope counts and the facets. The join to the record is restricted to the
  newest one, and the server-rendered table takes the same fix.
- Column catalogue: applicant (fixed), waiting, group, one `answer:<key>` per profile field and question the
  reader may see (`fields::resolve()` and `answers::fields()`, masked by `submission::visible_keys()`, as
  the report's `questions()`), comment, earlier applications (CQ-D8). An answer the reader may not see is
  not a column, not a filter and not in the row.
- Facets with counts: group, how the group was named (chosen, key, none), waited more than, applied before.
  A count is what choosing the option would list, as in the report.
- Web services, `ajax => true`, each one deriving the context from the method id, refusing guests and
  asking `listing_scope()` what the page asks: `enrol_apply_queue_rows` (scope, filters, search, page, page
  size, sort), `enrol_apply_queue_summary` (the tiles), `enrol_apply_set_queue_preference` (columns,
  widths). Returns are allowlists; cells are server-formatted HTML as the report's.
- The applicant cell carries the profile picture (core's `user_picture`, MQ-D9) and the tags the queue
  already computes (Deferred, the key's outcome, Applied before, Yours), and whether the row may be selected
  (never the reader's own, never for a viewer).
- Tests: the headless rows equal the table's rows, in set and order, for each scope clause (narrowed
  moderator, viewer, own application, no record, two records); every masked field is absent from columns,
  filters and rows; each facet's count is what choosing it lists. Mutation gates for each guard.
- `security-diff-read` on the diff; an MDL Shield review if the owner asks for one.
- Measured on the R6 seed (10,000 applications) on m503 and on the mini cluster before the client is built:
  a page in a bounded number of statements, the facets in one read per distinct WHERE.

### Stage 2: the report's client becomes shared parts (tier 2)

- The report's `Bar`, `Grid`, `Cards`, `Pager`, `Segmented`, the menus and the filter panel move under a
  common directory and take props for what differs: the scope's vocabulary, the presence of Download, the
  row action, a leading selection column, the applicant cell's extras. The report's behaviour and its
  tests do not change; `tsc --noEmit` and the report's Behat hold that.
- The bundle: whether the fleet's bundle step builds two entries is not established. If it does, a second
  entry; if not, one bundle exporting two mounts. Decided by trying it, before any queue code.

### Stage 3: the queue view, read only (tier 2)

- `QueueView`: the tiles, the bar (All, Pending, Deferred; the count; Filters; Columns with its sets; View;
  the search that opens from its icon; the chips), the grid, the cards and the pager, over stage 1's
  services. Narrow form as the report's: by the bar's width.
- `manage.php` mounts it for the method queue on a scripting browser; `noscript` and `?view=table` keep
  today's table (CQ-D5). The review page is untouched.
- Behat: the scenarios that drive the queue with JavaScript are rewritten for the new page; the ones
  without JavaScript keep proving today's table.

### Stage 4: selection and decisions (tier 3: it drives the decision route)

- The selection column and "select all on this page"; the dock at the bottom with the count and the three
  decisions; the sheet with the message, the note, the groups and the role, as today's (MQ-D4 to MQ-D6),
  the choosers only for a holder of `enrol/apply:choosedecision`.
- The client submits a real form to `manage.php` with today's fields (CQ-D4), so every guard, the counts,
  the warnings about places and seats and the redirect are the ones that exist. Cancelling asks first.
- Tests: a decision from the new page writes exactly what the same decision from the table writes; a row
  the reader may not decide is not selectable and, forged, is left alone.

### Stage 5: finish (tier 1 and 0)

- The Design System's data report gains the selection variant and the dock (new D numbers in moodle-dev).
- README and its `queue.png`; CHANGELOG; the plugin's `CLAUDE.md` section on the queue.
- A browser pass on m503 and the mini cluster: narrow width, dark mode, a narrowed moderator, a viewer.

### Stage 6, later and separate: the wide queues

- The site-wide and mentee queues on the same client (the course column, the per-method messages, no
  answers for the mentee scope). Only if the owner wants them there (CQ-D3).

## 4. Risks

- **Two readers of one queue drifting.** Held by building the headless read on the table's own SQL and by
  the test that compares the two, as the report's walk is compared with its listing.
- **A new door onto answers.** The rows service shows what the page already shows the same reader; the
  masking is the report's rule, applied to columns, filters and rows, with a test per direction.
- **The bundle.** One entry today, and a test pins that shape.
- **Scale.** The queue's page is cheap today (50 rows, batch reads); per-answer columns add a read the
  report solved with one batch per page. Measured in stage 1, not assumed.
- **The frozen line.** Three other plugins call into this one; the frozen checkout keeps their older
  stacks working, and nothing is back-ported to it.

## 5. Order and size

Stage 0 first (it unblocks everything and is independent). Stages 1 and 2 can run in parallel. Then 3, 4, 5.
Stage 1 and stage 4 are the ones that ask for the matrix, a security read and a mutation sweep.
