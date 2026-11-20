# The audit report, rethought: a presentation layer over Report Builder

Status: **decided** (2026-11-20, round 2 the same day). The owner accepted every recommendation of
AR-D1&ndash;AR-D16 as written and decided AR-D17; nothing is started. Next: R1, after G5.
Interactive mockup: [`audit-report-mockup.html`](audit-report-mockup.html) (open in a browser; its
switches change concept, course size, viewport, view, reader and simulated latency, and it logs the
web service calls it would make).
Builds on [`audit-trail-analysis.md`](audit-trail-analysis.md) (the durable record and the read-side
`outcome`), [`group-review-scope.md`](group-review-scope.md) (S8: the report narrowed by groups),
[`group-screens.html`](group-screens.html) (V-D1&ndash;V-D14) and
[`applicant-group-choice.md`](applicant-group-choice.md) (C-D1&ndash;C-D29, the facts the report must
now carry), whose decisions stand.

## The request

The owner's words, kept verbatim:

> Quero que inicie um estudo (brainstorm de design) para melhorar o relatório de auditoria (enrol/apply/report.php), incluindo aqui pontos novos deste trabalho. Alguns pontos que me incomoda:
> * Não ser adaptado para celulares, mesmo no desktop é uma tabela interminável horizontalmente que não permite escolher as colunas.
> * Quem decidiu a candidatura não fica claro pelo nome da coluna e distante da data de decisão.
> * Importante ter a informação sobre tempo de espera entre a candidatura e a decisão, para avaliar tempo de retorno.
> Não sei quais são os limites da API de relatórios do Moodle, mas imagino que extração e filtragem de dados não são necessariamente limitadores do design do frontend, ou em outras palavras, podemos usar a API de relatórios e seus filtros mas ter uma framework visual (inclusive para nosso Design System) para exposição dos dados renderizados com componentes React, não?
> É um trabalho de instigação, estudo, proposição, nosso primeiro "cliente" será o enrol_apply.

Round 2, the owner's reading of the first mockup (2026-11-20), verbatim:

> No mockup não tem scroll horizontal, com muitas coluans é preciso para não quebrar em linhas demais. Ajustar o tamanho da coluna é possível? Não está disponível a visão de card, como seria? E quando houver centenas de grupos, scroll vertical? Lembrando que tudo deve ter alta performance, skeleton, carregamento assíncrono, etc. Profiling deve ser executado depois de implementado o código. A pesquisa via WS pode nome do candidato. Atualize o mockup.

> "90th percentile", "Distribution of the resolution" e "Median by" pode ser removido do moderador.

Section 5b answers round 2; AR-D13 to AR-D17 are its decisions (AR-D17 is the owner's).

Three defects (phones and width, the decider, the wait) and one question of architecture (can Report
Builder stay the data and filter engine while a React layer, drawn with the fleet Design System,
does the presentation, for enrol_apply first and the fleet after).

## Verdict

**Yes, and core already shows the seam.** Report Builder can stay the engine (access, base
conditions, the group narrowing, the filters and their per-user store, downloads) while something
else draws the rows: core's own web service does exactly that for the Moodle App
(`core_reportbuilder_retrieve_system_report`, below). What core does **not** give is the
presentation the owner asks for: no column choice by the viewer, no card view for a system report,
no median or percentile. Those are the plugin's to draw, and React is a good tool for it on 5.2 and
later. Two costs decide the shape: enrol_apply supports **5.1** on the same branch (`version.php:31`,
`[501, 503]`), where there is no React, and the fleet rule that each plugin draws on its own
(Design System `INDEX.md`: "none depends on another to draw"), which makes reuse a matter of a
contract and a reference implementation, not a shared library.

The recommendation (AR-D1) is staged: fix the content in Report Builder first (it lands on every
version and is the contract any frontend reads), then add one plugin web service that reads the
same report headlessly, then the React view on 5.2+, with core's own table kept as the fallback
and the download path.

## 1. What core's Report Builder gives, and what it does not (5.3)

Paths under `~/dev/moodle-503/public/`. "Custom report" is the one an administrator builds from a
datasource in the Report Builder UI; "system report" is a class a plugin writes, like
`course_applications`.

| Need | Core 5.3 | Evidence |
|---|---|---|
| Access on every request | A system report runs its own `can_view()` when built, on the page and on every AJAX request; custom reports use `permission::can_view_report` (capability, audiences) | `reportbuilder/classes/system_report.php:97-104`; `permission.php:92` |
| Column choice by the viewer | **None.** Core switches off `flexible_table`'s own hide/show of columns in both report tables. Columns come from the definition: code for a system report, the editor for a custom one | `reportbuilder/classes/table/system_report_table.php:173` and `custom_report_table.php:148`, `collapsible(false)`; no `toggleable` or column-visibility API in `reportbuilder/` |
| Phones | A **card view**, for **custom reports only**: under a 576 px container the table becomes cards, or always with `data-force-card`. The selector is scoped to `data-report-type="0"`, a custom report; a system report (type 1) never gets cards and scrolls sideways in `.table-responsive` | `theme/boost/scss/moodle/reportbuilder.scss:428-446`; `reportbuilder/classes/local/report/base.php:40,43`; card settings `form/card_view.php:75-77` |
| Aggregation | avg, count, countdistinct, sum, min, max, percent, groupconcat(distinct), date, week, month, year; usable by system reports too (`set_aggregation()` with options since 5.0); aggregated columns group by every other column. **No median, no percentile, no summary or totals row** | `reportbuilder/classes/local/aggregation/`; `local/report/column.php:537`; `table/system_report_table.php:105-131`; `UPGRADING.md` 5.0 (MDL-84537), 5.3 (MDL-84635) |
| Rows as data | `core_reportbuilder_retrieve_system_report`: builds the report with `system_report_factory::create()`, runs `require_can_view()`, returns `headers`, `rows[].columns` and `totalrowcount`, paged. Rows are the **formatted** cells (the columns' callbacks, HTML strings), not typed values; registered for the **mobile service only**, not `ajax` | `lib/db/services.php:3116`; `reportbuilder/classes/external/systemreports/retrieve.php:114-115`; `external/system_report_data_exporter.php:49-69,75-110` |
| Filters from another client | Values live per report and user in `reportbuilder_user_filter` (`user_filter_manager`), the same store for system and custom reports. `core_reportbuilder_set_filters` and `_filters_reset` are `ajax` web services and rebuild the report (so `can_view()` runs); the next row read applies them. **No web service returns the filter definitions**: they exist only as core's rendered filter form | `local/helpers/user_filter_manager.php`; `external/filters/set.php:69-76`; `local/report/base.php:833,842` |
| Where the rows come from | `base_report_table::init_sql()` ANDs the base conditions, the conditions and **this user's filter values** into one `table_sql::$sql` (`fields`, `from`, `where`, `params`, public) | `reportbuilder/classes/table/base_report_table.php:69-120`; `lib/table/classes/sql_table.php:44` |
| Sorting | Every column sorts by default since 5.3; a column sorts on its SQL fields, never on what a callback computes | `UPGRADING.md` 5.3 (MDL-87404); `local/report/column.php:629-667` |
| Downloads | `reportbuilder/download.php` rebuilds the report and checks `can_view() && is_downloadable()`; every dataformat core has | `reportbuilder/download.php:20-60`; `system_report.php:325` |
| Schedules and audiences | Custom reports only | `local/helpers/schedule.php:186`; `task/send_schedule` |
| React | Core ships the React runtime and import map on 5.2+ (`lib/js/esm/src`, `react_autoinit`), but nothing of Report Builder or `core_table` is React: Report Builder is AMD only | `lib/js/esm/src/`; `reportbuilder/amd/src/` |

**So, to the owner's question.** The extraction and the filtering are not the limit. A plugin can
(a) build its system report as it does today, (b) let core keep the filter values per user, and
(c) read the rows through a web service of its own written exactly like core's exporter
(`system_report_table::create()`, `setup()`, `query_db()`, then `rawdata` and `format_row()`), returning
typed values beside the formatted ones, with `ajax => true`. The report's `can_view()`, its base
conditions (the course, S8's group narrowing) and its formatters (the snapshot masking of
`visible_keys()`, identity columns through `core_user\fields`) all run on that path because the
report object is the same one. And the same `table_sql::$sql` gives a summary query over **exactly**
the rows the reader sees, filters included, with fields of its own.

What stays core's and is lost by a fully custom frontend: core's filter form (it would have to be
reused in a sheet, or redrawn), the dynamic table's paging, and the "download" button (kept by
linking to core's `download.php`, which rebuilds the same report).

## 2. Today's report, measured

Measured on m501 (`enrol/apply/report.php?id=239`, the README's demo course, 7 records, one method,
one identity field and two questions), 2026-11-20, logged in as admin.

| Fact | Measure |
|---|---|
| Columns | **17**: First name / Last name, Email address, Status, Submitted on, Decided on, Comment, Note for the record, Enrolment now, Outcome, Admission route, Group of the enrolment key, Groups joined, Details submitted, City/town, How much Python have you written?, What would you like to use it for?, **Full name** |
| Width at a 1440 px window | table **1680 px** in a 1379 px container: 300 px of sideways scroll on a desktop |
| Width at 375 px | the same 1680 px table in a **359 px** container, 4.7 screens wide; the first row is 273 px tall; no card view (it is a system report) |
| Filters | 13 |
| The decider | the **17th** column, headed **"Full name"** (the user entity's own title; the entity title "Decided by" is set at `course_applications.php:137` but a system report's header shows only the column title), **12 columns** away from "Decided on" (the 5th) |
| The same data twice | "Details submitted" (the snapshot) repeats City/town and both answers, which have their own columns (`course_applications.php:228-241`) |
| The state, three times | Status (the record), Enrolment now (the live enrolment) and Outcome (the two read together, `audit-trail-analysis.md`) sit side by side; a reader has to learn which one to believe |
| Dates | "Monday, 26 October 2026, 2:17 AM", 32 characters, twice per row (`format::userdate`, `formatters/submission.php:330`) |
| The wait | **absent.** Not a column, not a filter, not a metric |

Columns a course will grow into with this year's work: the method (two per course with the
moderator recruitment of C-D19), the **chosen group** (`chosengroupid`, C-D4; G5 adds the column),
the role decided (`decidedrole`), one column per question per method, and every identity field the
site names. A real course reaches 20 to 25 columns.

### What the wait can mean, from the record as it is

The record keeps one decision: `submission::decide()` overwrites `status`, `timedecided` and
`decidedby` each time (`classes/local/submission.php:315-337`). So:

| Case | `timedecided - timecreated` today | What the reader needs |
|---|---|---|
| Pending (status 0) | not defined (`timedecided = 0`) | the **age**: now minus `timecreated`, "waiting 4 d", open-ended |
| Approved or cancelled once | the turnaround | the turnaround |
| Deferred (status 2) | the time to the deferral | the time to the first response, and that it is still open |
| Deferred, then approved | the time to the **approval**; the deferral's date is gone | both: first response and resolution |
| Re-decided (approve, suspend, approve again: `$isfreshdecision`, `lib.php:907`) | from the application to the **second** approval, which may be months | the first decision; a re-decision is not a slow one |
| Admitted by a key (`keyoutcome = 1`) | about zero, and `decidedby` is the **applicant** (`lib.php:773`, G11) | "by key", outside any human turnaround |
| Reconstructed by `ensure()` | from the enrolment's date (`submission.php:260-280`) | as is: it is the honest date |

Core's log has every decision as an event (`application_decided`, `application_deferred`,
`application_admitted_by_key`), but the log is pruned by the site's lifetime setting and is not a
durable record. The first response therefore needs a column of its own if it is to be measured
(AR-D4).

## 3. Concepts

Every concept starts from the same **content** (section 3.0), because it is what the owner asked
about and it lands on every version. They differ in who draws it.

### 3.0 The content every concept shares

- **The decision in one cell.** "Decided" holds the date and, under it, "by Maria Souza"; a key
  admission reads "By key: Evening A"; an undecided one "Not yet". Headed "Decided", with "Decided
  by" kept as a column of its own for sorting, filtering and export. Fixes defect 2 at its root:
  the person and the date are one fact.
- **The wait.** A new entity column `waittime` (seconds, `TYPE_INTEGER`, sortable in SQL):
  `timedecided - timecreated` when decided, `:now - timecreated` when pending, shown as "3 d 4 h";
  a pending one reads "4 d, open". A filter "Waited more than N days". Both in the custom-report
  datasource too, where `avg`, `min` and `max` aggregation come free.
- **One state.** "Outcome" (the read-side column already shipped) becomes the only state column in
  the default set, drawn as a Design System status label with the record's status under it when the
  two differ ("Approved, now unenrolled"); Status and Enrolment now stay available, off by default.
- **The group, once.** One "Group" column: the key group ("Key: Evening A") or the chosen group
  ("Chose: Morning C", C-D4), then the groups joined after an approval. Replaces "Group of the
  enrolment key" and "Groups joined" in the default set.
- **The method**, shown only where the course has more than one (as its filter already is).
- **Short dates** (`strftimedatetimeshort`), the full date in a `title`.
- **The snapshot column out of the default set** where the per-question columns exist (it repeats
  them); kept for downloads and custom reports.
- **A default column set of seven**: Applicant (name, identity field under it), Outcome, Submitted,
  Wait, Decided, Group, Method. Everything else is chosen (where a concept allows choice).

### Concept A: Report Builder, restyled

The system report stays the page. 3.0's content, plus a plugin stylesheet that turns this report's
table into cards under a narrow container, copying core's `table-cards` mixin for
`[data-enrol-apply-report]` (an attribute set with `add_attributes()`), because core's card view
skips system reports.

- **Costs:** small (tier 2 for the columns, tier 3 if AR-D4's column lands). No JavaScript.
- **Keeps:** everything: `can_view()`, S8 narrowing, masking, core's filters and their store,
  sorting, paging, downloads, 5.1.
- **Loses:** nothing it has today; but **no column choice** (core has switched it off), no summary
  band, and cards whose fields are the table's, in the table's order, with labels from `data-cardtitle`
  that core sets only for custom reports (the plugin would add them in `format_row`-time attributes, or
  live without).
- **Cluster:** nothing new. **Fleet reuse:** a CSS recipe, nothing more.

### Concept B: Report Builder with the viewer's columns and a summary, server-rendered

A, plus two things drawn in Mustache and a small AMD module:

- **Columns chosen by the viewer, stored as a user preference** (`enrol_apply_report_columns`)
  **read in `initialise()`**. `initialise()` runs on every request, the AJAX ones and the download
  included, so the report adds only the chosen columns: the choice is a server fact, the download
  follows it, and sorting and paging never meet a hidden column. A "Columns" menu (Design System
  `menu.md`, `menuitemcheckbox` items, three presets: *Decisions*, *Answers*, *Groups*) writes the
  preference through a plugin web service (or `core_user_update_user_preferences` once lib.php
  declares the preference) and reloads the table. Never a way around access: a column the reader
  may not see (identity, masked question) is never offered, because the menu is built from the same
  `questions()` and `get_identity_columns()` the report uses.
- **A summary band** above the table: decided, pending, median wait, 90th percentile, oldest
  pending, from **the table's own `$sql->from` and `$sql->where`** with fields of the summary's own
  (`base_report_table.php:69-120`): the same course, the same narrowing, the same filters, by
  construction. Percentiles computed in PHP over the decided waits (one indexed query on
  `courseuser`; a course holds hundreds to a few thousand records), because the DML layer has no
  portable percentile and the fleet rule wants DB-family SQL only beside a portable path.

- **Costs:** medium: one AMD module, two templates, one web service, the summary class, tests.
- **Keeps:** all of A, 5.1 included.
- **Loses:** the look of the fleet's filter bar (core's filter button and form stay), cards still
  CSS-only, the band is static HTML.
- **Cluster:** a preference is shared state (the database); the summary is computed per request; no
  cache, no cron, nothing node-local.
- **Fleet reuse:** a pattern (preference-driven columns, summary from `$sql`) any system report can
  copy.

### Concept C (recommended target): Report Builder as the engine, React as the presentation

The system report stays the single definition of rows, columns, access and filters; it stops being
the thing the reader looks at on 5.2+.

```
report.php ──► course_applications (system report: can_view, base conditions, S8 narrowing,
               │                     columns per the viewer's preference, formatters, filters)
               ├─► {{#react}} ApplicationsReport mount, props: report id, the column catalogue,
               │                 strings, the filters in force, the download url
               └─► core's own rendering inside <noscript> and as the "Table view" fallback

React (js/esm/src) ──ajax──► enrol_apply_report_rows      (plugin WS: rows, typed + formatted)
                     ├────► enrol_apply_report_summary   (plugin WS: band metrics over $sql)
                     ├────► core_reportbuilder_set_filters / _filters_reset  (core's store)
                     └────► enrol_apply_set_report_preference (the preference B reads)
download ───────────────► core's reportbuilder/download.php (unchanged)
```

- **The rows web service** is core's exporter rewritten for typed values: `system_report_factory::create()`
  from the enrol instance's **server-derived** course context, `require_can_view()`, then
  `system_report_table::create()`, `setup()`, `query_db()`; for each active column it returns the
  formatted cell (`format_row()`, where masking and `format_string` already ran) **and** the raw
  value for the few columns the client computes with (timestamps, wait seconds, status, ids). It
  never takes SQL, a column definition or a context from the client; a requested column list is
  intersected with the report's active columns.
- **Filters** stay core's: the React layer posts values with `core_reportbuilder_set_filters` and
  reads rows again. Its filter panel (Design System `filter-panel.md`) needs the definitions, which
  core does not export; the plugin returns them with the rows (name, type, options), built from the
  report's own `get_filter_instances()`, so the panel cannot offer a filter the report would not
  apply. For a filter type the panel cannot draw (core's date ranges, text operators), a "More
  filters" link opens core's own form in a modal (`core_reportbuilder\form\filter` is a dynamic form).
- **Presentation**: the Design System's filter bar (count, Filters with its loud counter, Columns,
  View, search), chips for the filters in force, a table at desktop widths with sortable headers
  (`aria-sort`), cards under a narrow container with the four fields that answer "who, what
  happened, how long, who decided" and the rest behind "More", the summary band with a wait
  histogram and a breakdown by method and group.
- **Costs:** large for enrol_apply alone (an ESM bundle, `tsc --noEmit` gate, three web services,
  React tests, Behat on 5.2/5.3), smaller for each later plugin that reuses the contract.
- **Keeps:** access, narrowing, masking and filters (they run in the same report object), downloads,
  custom reports and their schedules and audiences (untouched), 5.1 (it keeps core's table, with
  3.0's content).
- **Loses / owes:** two renderings to keep honest (core's table is free, but its column set must stay
  the React one's: the preference is the single source); accessibility of a hand-drawn table is the
  plugin's (a real `<table>` on desktop, a list of `article`s on a phone, live-region counts);
  the Moodle App reads core's WS, not this one.
- **Cluster:** every piece is per request and in shared stores (the database for preferences and
  filters, core's revisioned JS cache for the bundle). Nothing on a node, nothing in cron.
- **Fleet reuse:** the contract (section 4).

### Concept D: custom reports only

Put 3.0's new columns (`waittime`, the decided cell, the group) in the **datasource** and let
administrators build what they need in the Report Builder UI: card view on phones, column choice by
the report's author, `avg`/`min`/`max` of the wait, schedules, audiences. Costs least. But it is a
different reader: the datasource is **deliberately not narrowed** by groups (V-D11), the author
chooses the columns rather than the viewer, the teacher who opens the course's report gets nothing,
and there is no median. **A complement to every concept, not a replacement**: the new entity
columns reach the datasource in the same stage either way.

### Comparison

| | A | B | C | D |
|---|---|---|---|---|
| Phones | CSS cards (table order) | CSS cards | designed cards | core cards (custom only) |
| Column choice by the viewer | no | yes (preference) | yes (same preference) | by the author |
| Decider next to the date | yes | yes | yes | yes |
| Wait column and filter | yes | yes | yes | yes, with avg |
| Turnaround summary (median, p90) | no | static band | band, histogram, breakdown | avg only |
| S8 narrowing, masking, `can_view()` | kept | kept | kept (same report object) | not narrowed, by design |
| Downloads | kept | kept, follow the columns | kept (core's download) | kept |
| Schedules, audiences | n/a | n/a | n/a | yes |
| Moodle 5.1 | yes | yes | core's table (A's level) | yes |
| Fleet Design System look | partial (CSS) | partial | full | none |
| Reusable by another plugin | a recipe | a pattern | a contract and a reference | n/a |
| Size | S | M | L (then M per plugin) | S |

## 4. The fleet presentation layer ("first client is enrol_apply")

What C turns into for the fleet, if the owner takes it:

- **A Design System component, "Data report"** (`moodle-dev/docs/design-system/components/data-report.md`):
  the summary band, the table, the card form, the column chooser, the empty and loading states, built
  from components the system already decided (filter bar, chip, menu, counter, status label,
  skeleton, empty state). It is a spec, as every DS component is.
- **A server contract**, the same for every plugin: a system report class is the engine; the plugin
  adds a rows web service (`<component>_report_rows`) returning `{columns: [{key, title, type,
  sortable, group, default}], rows: [{id, cells: {key: {raw, html}}}], total, filters: [...],
  appliedfilters}` and a summary web service returning named metrics. An abstract base (copied, not
  depended on) holds the exporter loop, the column intersection and the preference read.
- **A client reference**, `ReportView.tsx` and its parts in enrol_apply's `js/esm/src`, copied into the
  next plugin with its prefix changed, the way the Design System's markup is today. A shared
  dependency plugin would reuse more code but put an install requirement on every plugin in the
  directory and break "none depends on another to draw" (AR-D10).
- **Candidates for a second client**, each a report-shaped surface today: `local_groupdist`'s
  distribution reports, the IV analytics report (`get_report`, measured at scale), evidence hub,
  `block_feedback_tracker`'s lists, `local_rank`. The second client is where the contract is proved;
  the first only shows it can be drawn.

## 5. Security and cluster notes for whichever concept

- The course comes from the enrol instance id, never from the client; the report's own base
  condition remains the boundary (as `report.php`'s comment says today).
- The summary query reuses `$table->sql` and so cannot widen the rows; a test pins that a narrowed
  reader's decided + pending counts equal their table's `totalrows`, and one that a filter narrows
  both alike.
- A breakdown **by decider** ranks named colleagues by speed. It reads only names the reader can
  already see in the Decided column, but it is a people-management view; AR-D7 asks whether to show
  it, and to whom.
- The column chooser never lists an identity column or a masked question the reader may not see;
  the preference is cleaned against the report's catalogue on read, so a forged key is dropped.
- Web services: `ajax => true`, `validate_context()` from the server-derived context,
  `require_capability('enrol/apply:viewreports')`, returns as an allowlist, `current_language()` in any
  cache key (none is planned), `format_string(..., ['escape' => false])` where a `PARAM_TEXT` return
  would meet a bare `<` (fleet rule).
- Cold node, fresh cron: nothing to rebuild; the bundle is core's revisioned JS; the preference and the
  filters are database rows. No mini-cluster check is needed beyond the usual, because nothing is
  node-local.

## 5b. Many columns, many groups, and performance (round 2)

What the mockup now shows, and what each piece costs in concept C. None of it exists in core's table
(concept A keeps core's behaviour: the header scrolls away, widths are the browser's, no column choice).

**Many columns: a bounded grid that scrolls both ways.** The rows sit in a frame of their own (about
34rem high on a desktop) that scrolls sideways and down; the heading row is `position: sticky` at the
top and the Applicant column at the left, with a shadow on its edge once the grid has scrolled, so
the reader never loses who a row is about. Cells keep one line and end in an ellipsis, the full value
in the cell's `title` and in the card view; two lines at most where a cell has a sub-line (the
decider under the date, the groups joined under the group). The page itself never scrolls sideways.
`table-layout: fixed` with a `<col>` per column makes the widths exact and the layout cheap: the
browser does not measure every cell of the page.

**Resizable columns.** A handle on each heading's edge (`role="separator"`, `aria-orientation="vertical"`,
`aria-valuenow` in pixels): drag it, or focus it and press the arrow keys (16 px a step); a double
click restores the default. Each column has a minimum (the Applicant 160 px, a date 104 px). A drag
changes only that `<col>` element, never re-renders the rows. The width is saved once, on release,
**in the same user preference as the column choice** (AR-D13), so a reader's grid is the same on
every course and node; Columns ends with "Reset column widths". Widths are a presentation fact, so
the download ignores them.

**The card view.** View (the bar's view button, a menu per `menu.md`: Automatic, Table, Cards; plus
"Core's table view") chooses it. Automatic is cards under a 576 px container and the table above.
On a wide screen the cards are a grid (`auto-fill`, 17.5rem minimum) and show every chosen field
under the four fixed lines; on a phone they are one column and the other fields wait behind "More"
(AR-D9). The same rows, the same page, the same order as the table.

**Hundreds of groups**, measured in the mockup with 240 groups in 20 groupings:

| Where | Up to 8 groups (RP-D18) | Above 8 |
|---|---|---|
| Filters → Group | the panel's toggle buttons with counts | a search field, a grouping select (RP-D23), the chosen groups pinned on top, then at most 50 matches in a list that scrolls inside the panel (15rem), and "190 more: refine the search or pick a grouping" |
| Group cell | "Chose: Recife 03", "Joined Recife 03" | the same, with at most two joined groups named and "+N" after them; every name in the `title` and the card |
| Median by group | one row per group | the 8 groups with most decisions, then "All 240 groups, slowest first": a searched list that scrolls inside the panel (16rem) |

The panel's options and counts come with the first rows response (the report's own filter
instances, intersected with what the reader may see), so opening Filters costs no request. Group
names follow `groupvisibility::visible_to()` for the reader, as everywhere else.

**Asynchronous loading, in three regions.** `report.php` renders only the shell: the heading, the
bar, and the React mount with its props (the column catalogue, the reader's preference, the strings,
the filters in force). Then two requests go out in parallel:

| Region | Web service | First load | Later loads |
|---|---|---|---|
| Rows | `enrol_apply_report_rows` (page, perpage, sort, scope, search; filters from core's store) | a skeleton in the shape of the grid or the cards (`skeleton.md`: blocks, one 1.3 s pulse, `aria-busy`, one visually hidden status line) | the old rows stay, dimmed, under a 2 px progress line: no layout jump, the reader keeps their place |
| Band | `enrol_apply_report_summary` (the same filters, the measure) | the band's skeleton (three or four tiles, two panels) | as the rows |
| Preference | `enrol_apply_set_report_preference` | none | written on a column change and on a width release; nothing waits on it |

One request in flight per region: a newer one supersedes the older (an `AbortController` in the
client, so a slow answer never paints over a newer one). A sort or a page reloads the rows only; a
filter, the scope or the search reloads both; the measure reloads the band (and the rows only when
they are sorted or filtered by the wait); a column change reloads the rows, because the columns are a
server fact read in `initialise()`. The mockup logs each call with its simulated latency
(0.4 s or 1.5 s).

**The search by name goes through the web service.** It is a parameter of the rows call, never a
stored filter (writing core's filter store on every keystroke would be a database write per letter),
applied as one more condition appended to the report table's `$sql->where` before `query_db()`,
built with the same SQL core's text filter uses for the applicant's full name:
`$DB->sql_like($fullnamesql, :q, false, false)`, case- and accent-insensitive
(`reportbuilder/classes/local/filters/text.php:131`). The client sends it 300 ms after the last key,
from two characters, with a spinner in the field; the chip "Name: mar" shows it in force and removes
it. The summary call takes the same parameter, so the band follows the search. Only the name: an
identity field (the e-mail address) would be searchable only where the reader may see it, which is
AR-D15.

**Server cost, by design.** Paging, sorting and filtering are SQL on the server (30, 50 or 100 rows a
page); the client never holds more than one page. The rows call is one data query and one count
query whatever the page size, plus the formatters' batched reads (the snapshot's one batch query, the
groups' visibility for the page), never a query per row. The summary is one query over
`$table->sql` returning the waits of the filtered human decisions and their group and method (one
integer and two ids per decision), from which PHP computes the median, the 90th percentile, the
histogram and the per-group medians; at 10,000 decisions that is about 240 KB read once. If profiling
says it is too slow, two steps in order: the histogram and the counts as SQL `CASE` buckets with
`GROUP BY`, then a MUC application cache keyed by course, reader scope and a hash of the filters,
stamped with a course revision that `submission::create()` and `decide()` bump (shared state, so a
cold node and a fresh cron need nothing; cluster-first). No cache is planned until a measure asks for
it.

**The moderator's band is compact** (AR-D17, the owner's decision): the median, what is still open and
the key admissions; no 90th percentile, no distribution, no breakdown. The summary call computes only
what the reader's band shows, so it is cheaper for them too.

**Client cost.** The React bundle is loaded by core's import map only on `report.php` (5.2+), built by
`mdl grunt`; rendering 30 to 100 rows needs no virtualisation, and the long lists (groups) are capped
and scrolled rather than virtualised. Budgets in AR-D16.

**Profiling comes after the code, as a stage of its own (R6).** On a seeded course (10,000 applications,
300 groups, two methods, 20 deciders) on m503, then on the mini cluster:

- the server: each web service 50 times, p50 and p95 of its wall time, and its database statements
  (`perf_get_reads()` counts statements, three for a recordset on PostgreSQL), with `MDL_PERF` on; the
  three queries under `EXPLAIN ANALYZE` on PostgreSQL and MariaDB, to see whether the `courseuser`
  index carries the summary or it needs one on `(courseid, timedecided)`;
- the browser: a Performance trace of the first load (time to the band and to the first rows) and of
  a filter change, the React Profiler's render time for one page, the interaction latency of a sort,
  a resize drag and a keystroke in the search, at 1x and 4x CPU throttling;
- each figure against AR-D16's budget, in the pull request; a figure over budget is a finding, not a
  footnote.

## 6. Decisions

Each with its recommendation in bold. **Decided by the owner (2026-11-20): every recommendation of
AR-D1&ndash;AR-D16 accepted as written**; AR-D17 decided in round 2. AR-D16's budgets stay targets
until R6 measures them.

**AR-D1. Which concept.** A, B, C or D. **C as the target, reached in stages (section 7): 3.0's
content in Report Builder first (every version), then the headless web services, then the React view
on 5.2+. D's columns ride the first stage.** B's server-rendered chooser and band are built only if the
owner keeps 5.1 on the same branch for long (AR-D2).

**AR-D2. Moodle 5.1.** React needs 5.2. (a) 5.1 keeps core's table with 3.0's content and the CSS
cards (A's level); (b) B's chooser and band also on 5.1; (c) give enrol_apply a branch per version
(the fleet plan) and drop 5.1 from `main`. **(a) now, (c) when the branches are cut**: the column
preference is read by the server, so 5.1 readers still get the chosen columns once a 5.2 view has set
them, but no chooser of their own.

**AR-D3. The default column set.** **Applicant (identity field under the name), Outcome, Submitted,
Wait, Decided (date and decider), Group, Method (only with two or more methods).** The rest are
chosen.

**AR-D4. What "wait" measures.** (a) As recorded: to the last decision; (b) add
`timefirstdecided`, stamped once by `decide()` and never overwritten, and show "first response"
and "resolution". **(b)**: a deferral is a response, and a re-decision is not slowness. Tier 3 (one
column, an upgrade step, backup, privacy export). Records older than the column read their last
decision, labelled so.

**AR-D5. What counts in the turnaround metrics.** **Human decisions only: key admissions ("By key")
and records without a decider are out of the median, and shown as their own count.** Pending ones
count as "open" with their age, never in the median.

**AR-D6. Calendar or working time.** **Calendar time, shown as days and hours.** Working hours need
a calendar the plugin does not have; a median in calendar days is honest and comparable.

**AR-D7. Breakdown by decider.** (a) Not shown; (b) shown to every `viewreports` holder; (c) shown only
to a new capability. **(a) for now**: the band breaks down by method and group; a per-person view can
come later behind its own capability if the owner wants it, since the custom-report datasource
already lets a manager build it.

**AR-D8. Where the column choice lives.** **One user preference for the whole site, keyed by
column**, with the per-question columns as one "Answers" switch plus individual ones; a course
whose questions differ shows what the preference names and exists there.

**AR-D9. Phones.** **Cards of four lines (who; outcome; wait and submitted; decided by whom and
when) with "More" opening the other chosen fields**, switched by the report's container width, not
the window's.

**AR-D10. How the fleet reuses it.** (a) A Design System spec plus a copied reference implementation;
(b) a shared dependency plugin. **(a)**, the Design System's own model.

**AR-D11. The snapshot column.** **Out of the default set and of the chooser where the report already
has per-question columns; kept in downloads and in the datasource.**

**AR-D12. A target for the wait.** (a) None; (b) a per-method "decide within N days" that colours the
wait and counts the overdue. **(a) now**; (b) is a new setting with its own help and is easy to add
once the median is visible.

**AR-D13. Column widths.** (a) The browser's; (b) resizable, saved in the column preference; (c)
resizable for the session only. **(b)**: one preference holds the reader's grid (which columns, how
wide), the same on every course and node; "Reset column widths" undoes it.

**AR-D14. Paging.** (a) Pages of 30, 50 or 100 chosen by the reader; (b) "Load more" (an endless list).
**(a)**: an audit report is read by position and exported by page set, and an endless list grows the
DOM without bound on a 2,400-row course.

**AR-D15. What the search matches.** (a) The applicant's name only; (b) the name and the identity
fields the reader may see. **(a)** as the owner asked; (b) later, built from
`get_identity_filters()` so a field the reader may not see is never searchable (a search is a filter,
and a filter on a hidden field recovers its value by counting rows).

**AR-D16. Performance budgets**, to be confirmed by R6's measures: rows call p95 under 300 ms and the
summary under 500 ms at 10,000 applications on m503; a constant number of database statements per call
(not per row); the first rows painted under 1 s after the page; a sort, a filter or a keystroke
answered (dimmed rows or spinner) within 100 ms, and an interaction under 200 ms at 4x CPU throttling.
**Accept these as targets**, revised by the first profile rather than guessed again.

**AR-D17. The moderator's band. Decided by the owner (2026-11-20):** a reader narrowed by separate
groups sees the median, what is still open and the key admissions; the 90th percentile, the
distribution and the breakdown are removed for them.

## 7. Stages

| Stage | Content | Tier |
|---|---|---|
| R1 | 3.0's content: `waittime` column and filter, the "Decided" cell, one Group column (key or chosen, after G5's helper), one Outcome cell, short dates, the default set, the snapshot out of the default; the same columns in the datasource; CSS cards for the system report (A). Lands on 5.1&ndash;5.3 | 2 |
| R2 | AR-D4: `timefirstdecided`, the upgrade step, backup and restore, privacy, `decide()` stamping it once | 3 |
| R3 | The engine made headless: the column preference read in `initialise()`, `enrol_apply_report_rows`, `enrol_apply_report_summary` (over `$table->sql`), `enrol_apply_set_report_preference` (columns and widths, AR-D13); tests that the rows and the summary obey S8 and the filters | 3 |
| R4 | The React view on 5.2+ (`js/esm/src/report/`): filter bar, chips, columns menu, the bounded grid (sticky heading and Applicant column, resizable columns), cards, pager, summary band (compact for a narrowed reader), skeletons and superseded requests, the search by name; core's table as `<noscript>` and "Table view"; Behat on 5.3 | 2 (3 for its WS changes) |
| R5 | The fleet: `data-report.md` in the Design System, the contract written down, a second plugin chosen | 0 |
| R6 | Profiling of R3 and R4 on a seeded course (10,000 applications, 300 groups) on m503 and the mini cluster, against AR-D16 (section 5b); fixes it calls for, the cache only if a measure needs it | 1 to 3, by what the fixes touch |

With every decision taken, R1 can start; it should follow G5 for the chosen group. G5 (enrol_apply
2026112002) adds a "Group chosen" column and renames the group filter "Group (applied for or joined)";
R1 folds that column into the one Group cell of 3.0 and keeps G5's filter as is. R2 and R3 can follow
R1 in either order; R4 needs R3. R6 runs after R4 is implemented, never before: the owner's rule
(round 2).

## Sources read

`report.php`; `classes/reportbuilder/` (system report, entities, formatter, group filter, datasource);
`classes/local/submission.php` (`create()`, `ensure()`, `decide()`); `lib.php:773,907,1868,1941`
(the four `decide()` callers); `db/install.xml`; `lang/en/enrol_apply.php`; `CLAUDE.md`'s report,
scope and audit-trail bullets; `docs/design/applicant-group-choice.md`; core 5.3 as cited in section 1;
`moodle-block_compass/docs/adr/006-react-client.md` (the fleet's React precedent: `{{#react}}`,
`react_autoinit`, the `@moodle/lms/` import map for plugins); the fleet Design System (`INDEX.md`,
`tokens.md`, filter bar, menu, chip, counter, status label).
