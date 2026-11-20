# The confirmation queue, aligned to the Design System

Status: **decided** (2026-11-20). The owner accepted every recommendation of MQ-D1&ndash;MQ-D16 as
written; nothing is started. Next: Q1, then Q2.
Interactive mockup: [`manage-queue-mockup.html`](manage-queue-mockup.html) (open in a browser; its
switches change concept, queue size, viewport, reader and JavaScript on or off).
Builds on [`applications-desk.html`](applications-desk.html) (the queue's decisions of 2026-08-31,
mockup A approved), [`ui-rebuild-plan.md`](ui-rebuild-plan.md),
[`group-screens.html`](group-screens.html) (V-D1&ndash;V-D14),
[`review-page-concepts.html`](review-page-concepts.html) (RP-D1&ndash;RP-D28, the review page),
[`applicant-group-choice.md`](applicant-group-choice.md) (the chosen group, G5) and
[`audit-report-study.md`](audit-report-study.md) (its grid, filter bar and band), whose decisions stand.
Subject: `manage.php` with `id=` (one method's queue) or no parameter (the site-wide and mentee queues).
`manage.php?userenrol=`, the review page, is in scope only for what the Design System changes on it
(section 6).

## The request

Verbatim, from the owner (2026-11-20):

> Vamos continuar com o estudo agora para a página de Confirmação das inscrições
> (enrol/apply/manage.php). Alinhar a página ao DS, propor mockup. Observações para o próximo estudo, na
> página de "confirmar inscrições" ter a foto de perfil do usuário (algo que não precisa na página de
> auditoria) e manter o sticky footer para ações.

The same message asked about the audit report: "senti falta de um botão de ação para levar a análise
individual de uma candidatura ainda em aberto. Ou esse poderia ser o comportamento ao clicar no nome do
usuário?" Answered in [`audit-report-study.md`](audit-report-study.md) as AR-D18: the name stays the profile link
(the owner's decision of 2026-08-31, which the queue follows too), and an open application gets a "Review"
button beside it.

## Verdict

The queue is functionally right and visually behind. Two years of decisions made its behaviour
deliberate (a POST contract, a working no-JavaScript path, per-row authorisation, filters in the url,
chips as links), and none of that needs to change. What needs to change is what the decider sees:

- the filters take a form of seven labelled boxes and push the first row 723 px down on a desktop and
  1,099 px down on a phone;
- a row is 264&ndash;283 px tall (430&ndash;474 px on a phone), because every answer is a stacked tile;
- the one link that opens an application sits in the last column, off screen at a common width;
- the decision options sit below the whole queue, 2,100 px from the first row with seven rows, and the
  sticky footer that applies them says nothing about them.

**Recommendation: concept A, a restyle in place** (Mustache, CSS and the existing AMD module, on every
supported version), drawn with the same Design System parts as the audit report so the two pages read as
siblings: the filter bar with a status segmented control, a compact capacity band, a bounded grid with the
applicant column fixed and the profile picture in it, a "Review" button that is always visible, and a
sticky footer of three buttons that opens a **decision sheet** holding the options. Without JavaScript
the same page keeps working as it does today.

## 1. Today's queue, measured

On m501 (Moodle 5.1, enrol_apply 2026112002), course DATA210, instance 239: 7 applications (2 deferred),
4 of 30 places, 11 of 40 applications held. Desktop viewport 1,214 px (content 804 px); phone 375 px.

| What | Measured | Why it matters |
|---|---|---|
| Page heading | "Enrol Confirm" (`confirmusers`), title case and not English; pt_br "Confirmar inscrições" is right | Rule 3 of the Design System (sentence case) and plain wording |
| Description | "Applications badged as on the waiting list have been deferred." | Explains a label the page could simply name |
| Capacity card | 161 px: tiles "7 Awaiting decision", "2 Deferred to the waiting list", meters "4 of 30", "11 of 40", Status "Open" as `badge bg-success` | Right content; Bootstrap badge instead of a status label; no link from a count to the rows it counts |
| Filters | 172 px: Search, Application status, Group, Applied from, Applied to, per-field boxes, "Apply filters" | A form grid where the fleet uses a filter bar; the table starts at **723 px** (desktop) and **1,099 px** (phone) |
| Table width | 928 px of content in an 804 px area | The **Review** column, the only link to the review page, is the 7th and last: off screen until the reader scrolls sideways |
| Row height | **264&ndash;283 px** desktop, **429&ndash;474 px** phone | "Submitted with the application" renders each answer as a stacked tile (28 tiles for 7 rows); the comment column wraps at about 80 px |
| Status in a row | `badge bg-warning` "Waiting list"; `badge bg-primary` "Valid group key, but a group had no seat left" | "Waiting list" contradicts the owner's word for the state ("Deferred", 2026-08-31) used everywhere else; a sentence inside a badge |
| Profile picture | core `user_picture`, size 35, initials when there is no photo, linked to the profile | Already there; the owner wants it kept |
| Decision options | Groups to join, Role, Message to the applicant, Note for the record, **below the table**: top at **2,855 px** with 7 rows (the table at 723 px); with 50 rows about 14,000 px down | The decider sets options for a selection they can no longer see, and the footer gives no sign that the options exist |
| Sticky footer | 80 px: "0 selected on this page", "With selected users..." select (Confirm requests, Defer requests, Cancel requests), "Go" | Three decisions hidden in a select; "Go" names nothing; cancelling (an unenrolment) is applied without a question on the queue, while the review page asks |
| Page length | 4,958 px desktop, 7,754 px phone, 7 rows | |

The review page (`?userenrol=434`) already has the shape the queue lacks: a sticky footer of three buttons
("Cancel this application", "Defer this application", "Confirm this application"). Its own Design System
gaps are in section 6.

### What stays, whatever the concept

These are contracts, each with a reason in `CLAUDE.md`, and the design keeps them:

- the decision is a POST of `formaction`, `userenrolments[]` and `sesskey` to `manage.php`, the same
  contract the review page posts;
- the queue works without JavaScript: paging, sorting, a GET search box, chips whose remove control is a
  link, and a footer brought on screen by the `body:not(.jsenabled)` rule;
- the GET filter form stays outside the POST decision form (HTML forbids nested forms);
- the sticky footer is core's, rendered inside the form and never moved in the DOM; only the action
  lives in it, because its content box is 80 px with `overflow: hidden`;
- selection is core's `checkbox_toggleall`, per page;
- per-row authorisation in every decision method; identity fields through `\core_user\fields`; the
  profile link only where `user/view.php` would open it (`profilelink`).

## 2. Concepts

### Concept A (recommended): restyled in place

The page stays a `core_table\dynamic` table with the plugin's Mustache templates and `amd/src/manage.js`.
Every part changes look and place, none changes contract. Works on 5.1, 5.2 and 5.3 from one branch.

### Concept B: master and detail

The list on the left, the selected application's review on the right, deciding one at a time without
leaving the page. It is the review page's concept B, which the owner did not choose (RP-D1, concept A),
and it doubles the review page's markup and gates in a second place. Not recommended.

### Concept C: React over a headless queue

The audit report's layer (AR-D1, AR-D10) applied to the queue: a rows web service, React on 5.2+. It buys
nothing the queue lacks today (the dynamic table already refreshes by AJAX and searches as one types,
decided 2026-08-31), costs the no-JavaScript path or a second implementation of it, and would leave 5.1 on
a different page. Worth revisiting only after the audit report's R4 has proved the layer; the restyle of
concept A uses the same tokens and parts, so nothing would be thrown away.

| | A: restyle | B: master and detail | C: React |
|---|---|---|---|
| Versions | 5.1&ndash;5.3 | 5.1&ndash;5.3 | 5.2+ (5.1 keeps today's) |
| No-JavaScript path | kept as is | needs a fallback page | needs a second implementation |
| New server code | none | a fragment endpoint | rows web service, as AR R3 |
| Matches the audit report | same parts and tokens | partly | same layer |
| Tier | 1 then 2 | 3 | 3 |

## 3. Concept A in detail

### 3.1 Heading and capacity band

- The heading becomes **"Confirm enrolments"** (`confirmusers`'s English value; the key and pt_br stay)
  (MQ-D2). The description line goes: the status labels say "Deferred" themselves.
- The capacity card becomes a **band of compact tiles** (the audit report's `.ea-tile`, 12 px radius):
  Places taken *4 of 30* with its meter, Applications held *11 of 40* with its meter, Status *Open* as a
  `success` status label with the closing date under it. The two plain counts (Awaiting decision,
  Deferred) move into the status segmented control (3.2), where they also act as the filter (MQ-D3). The
  band keeps rendering with an empty queue, which is when it explains something (today's rule). The
  site-wide and mentee queues have no instance, so they show no band, as today.
- The notices (places full, applications closed, key only, view only) stay Bootstrap alerts, which is
  core's own counterpart; the scope line ("You see the applications of: ...") becomes a quiet line under
  the band.

### 3.2 Filter bar

The audit report's bar (`filter-bar.md`), with the queue's own controls:

- **Status, a segmented control** (`segmented-control.md`): *All 7 &middot; Pending 5 &middot; Deferred 2*,
  each with its counter. It replaces the "Application status" select; its three values are the existing
  `status` parameter (empty, 1, 2), so the url and the no-JavaScript form stay the same (MQ-D3).
- **Search in place**, as today (`search`, as you type through the dynamic table, a GET box without
  JavaScript). Two characters, 300 ms, the same rule as the audit report's search (AR-D15).
- **The count**, "7 of 7 applications", right-aligned in the bar's cluster.
- **Filters**, an icon button with the loud counter of the filters in force. It opens the filter panel
  (`filter-panel.md`) with Group (search, grouping select and a scrolling list above 8 groups: RP-D18 and
  RP-D23, as in the audit mockup), Course category and Course (site-wide queue only), the per-field
  filters, Applied from and to, and "Clear filters" / "Show" in its foot. Without JavaScript the panel is a
  `<details>` holding the same fields and the "Apply filters" button, so the GET form is unchanged.
- **Chips** under the bar (`chip.md`): one per filter in force, each a link without its own filter, then
  "Clear all". Today's partial already has that contract; only its look changes.

### 3.3 The grid

- **Bounded and scrolling both ways**, as the audit report's grid (AR-D13 aside: the queue's columns are
  few and fixed, so no resizing): the heading row and the first two columns (selection and Applicant)
  stay put; a shadow appears on the fixed column once the grid has scrolled.
- **Applicant column**: the profile picture (core `user_picture`, 35 px, initials when there is none,
  linked to the profile where `profilelink` allows), the name as the profile link (the owner's decision
  of 2026-08-31), the status label beside it, the identity fields on the line under it, and a
  **"Review" button** (`btn btn-sm btn-outline-secondary`, `queuereview`) at the cell's right edge. The
  button leaves the last column, so it is visible at any width and on every row (MQ-D7, MQ-D9).
- **Status labels** (`status-label.md`): *Deferred* in the warning tone (not "Waiting list": MQ-D10);
  a key outcome in the info tone, by its short string ("Key accepted" exists; MQ-D11 adds short forms for
  the three "valid key, but..." outcomes), the full sentence in a tooltip and on the review page.
- **Group**: the application's group (G5: key group or chosen group), "+N" after two.
- **Applied**: the age ("36 d 2 h") on the first line, the date under it; the default sort stays oldest
  first.
- **Answers**: one line, "City/town: Québec &middot; Python: A little", then "+1" when more are
  configured; the full set is the review page's (MQ-D8). The comment: two lines, clamped, with the full
  text in the cell's title. A row becomes **about 64 px** instead of 264&ndash;283.
- Page size stays 50, core's paging under the grid.

### 3.4 Cards

Below a container width of 576 px (the report's container, not the window: the same rule core's card view
and AR-D9 use), each row becomes a card (MQ-D12):

1. checkbox, picture, name, status label;
2. group &middot; applied 36 d ago;
3. the answers line;
4. the comment, two lines; then the "Review" button.

No sideways scroll on a phone. The phone gets the same sticky footer.

### 3.5 The sticky footer and the decision sheet

The footer stays core's sticky footer (the owner: "manter o sticky footer para ações"):

| Left | Right |
|---|---|
| "3 selected on this page" (live), "Clear selection" | **Cancel applications** (`btn-outline-danger`), **Defer** (`btn-outline-secondary`), **Confirm** (`btn-primary`) |

The three buttons replace "With selected users..." and "Go" (MQ-D4): three submits named `formaction`
with the values `cancel`, `wait` and `confirm`, so the POST contract is unchanged. With nothing selected
they are `aria-disabled` (today's toggle-all rule, set by `manage.js`). On a phone the bar keeps one line, because core's
footer is 80 px with `overflow: hidden`: "3 selected", then "Cancel", "Defer" and "Confirm" (the short
labels; the sheet's title names the decision in full). Measured in the mockup: 64 px at 375 px.

**With JavaScript, a button opens the decision sheet** (`core/modal`, a dialogue on desktop and a bottom
sheet on a phone; `panel-popover-sheet.md`) instead of posting (MQ-D5):

- title: "Confirm 3 applications" (or Defer, or Cancel);
- the selected applicants: picture and name, the first five and "and 12 more";
- for Confirm: Groups to join (choosable groups, the same chooser and the same "kept" sentence as the review
  page, RP-D18 search above 8), Role to assign, Message to the applicants, Note for the record;
- for Defer: the message and the note (the reason for the record);
- for Cancel: the message and the note, and one sentence saying that cancelling removes the enrolment and
  the applicant's comment;
- foot: "Back" and the decision button with its count ("Confirm 3"), the destructive one in the danger
  style.

The sheet does not build a second form: it moves the page's own option fields (the `decision_controls`
partial) into the dialogue while it is open and back when it closes, so what posts is still
`enrol_apply_manage_form`. That is the one DOM move in the design, and it is of the options, never of the
sticky footer (whose rule stays).

**Cancelling from the queue now asks** (MQ-D6): the sheet is the question the review page already puts.
Without JavaScript the queue keeps posting directly, as today and as its Behat scenario proves.

**Without JavaScript** the options stay in the page, inside a `<details open>` titled "Decision options:
applied to every application you decide", placed **above** the grid rather than below it, so they are
where the decider starts and the footer's buttons apply them (MQ-D5).

### 3.5.1 A selection that spans enrolment methods

Asked by the owner (2026-11-20): "Como o modal de confirmação lidaria com métodos de inscrição
diferentes? Por exemplo, no caso entre o candidato a estudante e moderador juntas na seleção?"

**Where it can happen.** The `?id=` queue is one enrolment method, so its selection never mixes: the
student method and the moderator recruitment method of a course (C-D19, C-D20) are two instances with two
queues. A selection mixes methods only on the two queues that span instances: the **site-wide** queue
(no parameter, a system-level decider) and the **mentee** queue (a mentor). A course coordinator never
meets it, and a moderator narrowed by separate groups never sees a recruitment method's applications at
all (C-D20, "Who decides": only those who see every group).

**What a decision does there today, and keeps doing.** `decision_controls_context()` offers the group and
role choosers only when an instance is in scope (`renderer.php`), so the spanning queues show the message
and the note and nothing else; `confirm_enrolment()` allowlists per instance, so each approval takes **its
own method's** role and groups: the student gets Student and the student method's groups (or the chosen
group, G5), the moderator gets Non-editing teacher, Moderadores and the destination group. The roles are
right per row. What is wrong for a mixed selection is the **message**: one text, written once, reaches
both a future student and a future moderator.

**The sheet with more than one method** (MQ-D15, MQ-D16):

1. A line at the top: "This selection spans 2 enrolment methods. Each approval uses its own method's role
   and groups."
2. One section per method, in the order of the rows: the method's name and course, the count, the names
   (picture and name, five then "and N more"), and what an approval does, read from the method: "Approval
   assigns Student and joins the chosen group" / "Approval assigns Non-editing teacher and joins Moderadores
   and the chosen group". A method whose role can decide applications (the C-D29 rule: the role holds
   `enrol/apply:manageapplications` in the course) carries a warning status label, **"Grants a deciding
   role"**, because that approval hands somebody the power to approve others.
3. **One "Message to the applicants" box per method**, inside its section; one "Note for the record" for
   the whole decision (it records why the decider acted, which is the same act).
4. No group or role chooser, as today: they belong to one method's queue and to the review page. A line
   says so, with a link to each method's own queue ("Choose groups or the role: open this method's queue").
5. The foot: "Back" and "Confirm 4".

**What posts.** The page's one form, as everywhere: `formaction`, `userenrolments[]`, `sesskey`, the note,
and the messages as `outcomemessage[<enrolid>]`, one per method in the selection. `manage.php` groups the
posted ids by their method (read from `{user_enrolments}`, never from the post) and calls the decision
method once per method with that method's message, adding up what each one decided; the per-row checks
are unchanged. A plain `outcomemessage` (the no-JavaScript path and every single-method selection) still
applies to all, as today. Without JavaScript the mixed case keeps today's single message, and the options
details says, when the queue spans methods, "One message reaches every application you decide; decide each
method's applications separately to word them differently."

**The row says which method.** The spanning queues show a Course column today, which cannot tell two
methods of one course apart: its cell gains the method's name on a second line ("Python for data work
2027" / "Moderator recruitment"), and the same pair heads each section of the sheet.

**One person in both.** The lock of C-D29 refuses a student application from somebody who holds a moderator
one, but not the other order: a pending student application and a later moderator application of the
same person can both be in the site-wide queue. The sheet names that person in both sections, with
"also in the other section" under the second, so approving both is a choice and not an accident.

### 3.6 Empty, loading and long queues

- Nothing awaiting a decision: the empty state (`empty-state.md`), "Nothing waits for a decision", with
  the band above explaining a full method when that is the reason.
- Filters matching nothing: the compact line, "No application matches these filters", and "Clear
  filters".
- A refresh (search, sort, filter, page) keeps the rows on screen, dimmed, under a 2 px progress line;
  the first load is server-rendered, so no skeleton is needed. A newer request supersedes an older one
  (`AbortController` in `manage.js`).
- A queue of hundreds: paging stays at 50; the Group facet has the audit report's long-list behaviour;
  the site-wide queue's Course and Course category facets use the same search above 8.

### 3.7 Readers

- **A viewer** (`enrol/apply:viewapplications` without deciding): no selection column, no footer, no
  sheet, the view-only notice, and the "Review" button (the review page opens read-only for them). Today's
  rule (`candecide`).
- **A moderator narrowed by separate groups**: the group facet offers only their groups (V2); the band's
  numbers carry today's "in your groups" and "on this method" notes.
- **A mentor** on the mentee queue: no identity fields, no band, the same footer.

## 4. Security and cluster notes

- Nothing new crosses the server: the sheet moves existing fields and reads the selected rows' names from
  the page already rendered for this reader. The decision post and every per-row check are unchanged.
- The "Review" button points at `manage.php?userenrol=`, which every queue reader may open
  (`require_review_access()`: deciders and viewers in the same scope); a row the reader may list is a row
  they may open.
- The picture and the name link follow `profilelink`, as today; the sheet repeats the picture but not
  the link.
- The key outcome's short label names no group; the tooltip carries the same sentence the row carries
  today, so nothing is newly disclosed.
- Cluster: no new store. Filters stay in the url; the sort stays in `$SESSION->flextable` (sessions in
  Redis on the cluster); nothing is cached.

## 5. Costs

| Part | Files | Tier |
|---|---|---|
| Heading string, band, status labels, chips, filter bar look, grid and cards CSS, answers line, Review button in the Applicant cell | `lang/en`, `templates/manage.mustache`, `queue_capacity`, `queue_filters`, `queue_chip`, `classes/table/applications.php` (cell markup), `styles.css` | 1 to 2 |
| Status segmented control (the select's values), Filters panel over the GET form | `queue_filters.mustache`, `manage.js` | 2 |
| Footer buttons, decision sheet, cancel confirmation, `AbortController` | `manage_actions.mustache`, `manage.js` (+ `amd/build`), `renderer.php` | 2, Behat on 5.3 |
| Short key-outcome strings | `lang/en`, `lang/pt_br`, `classes/table/applications.php` | 1 |
| A selection across methods: the method on the course cell, the sheet's sections and "Grants a deciding role", `outcomemessage[<enrolid>]` read by `manage.php` and one decision call per method | `classes/table/applications.php`, `manage.php`, `manage.js`, `lang/en`, `lang/pt_br`; PHPUnit for the split (each method's rows get its own message, the per-row check still refuses), Behat on the site-wide queue | 2 |
| Review page Design System gaps | `templates/review*.mustache`, `styles.css` | 1 |

No schema, no web service, no capability: nothing reaches tier 3.

## 6. The review page, for the Design System only

Its layout is decided (RP concept A). Measured on `?userenrol=434`:

- the status chip (`badge enrol_apply-statuschip`), the meter counts and the "Next" link are drawn as
  pills: they become status labels (6 px) and a plain link with a chevron (D61);
- the three footer buttons are right: keep their order and styles, so the queue's footer copies them;
- the 100 px picture stays core's;
- the panels move to the band's tile look (12 px radius, the token chain).

## 7. Decisions

Each with its recommendation in bold. **Decided by the owner (2026-11-20): every recommendation of
MQ-D1&ndash;MQ-D16 accepted as written.**

**MQ-D1. Which concept.** A, B or C. **A**: a restyle in place on 5.1&ndash;5.3, sharing the audit report's
parts and tokens; C only after the audit report's R4 has proved its layer.

**MQ-D2. The heading.** **"Confirm enrolments"** as `confirmusers`'s English value (pt_br stays "Confirmar
inscrições"), and the description line removed.

**MQ-D3. Status as a segmented control.** **Yes: All, Pending, Deferred with their counts in the filter
bar**, replacing the select and the two plain count tiles; the band keeps places, applications held and
the method's status.

**MQ-D4. The footer's actions.** (a) Keep the select and "Go"; (b) three buttons. **(b)**: Cancel
applications, Defer, Confirm, in the review page's order and styles.

**MQ-D5. Where the decision options live.** (a) Below the table, as today; (b) in a decision sheet opened
by the footer's buttons, and above the grid without JavaScript. **(b)**.

**MQ-D6. Cancelling from the queue asks.** **Yes, through the sheet, with JavaScript**; without it the queue
posts directly, as today.

**MQ-D7. The way to the review page.** **A "Review" button in the Applicant cell**, always visible; the
name stays the profile link (decided 2026-08-31).

**MQ-D8. The answers in a row.** (a) Every answer, as today; (b) one line with "+N", the full set on the
review page. **(b)**.

**MQ-D9. The profile picture.** **Core's `user_picture` at 35 px in the grid and on cards, 24 px in the
sheet's list**, initials when there is no photo; round, because it is core's picture and not a control
(rule 2 of the Design System governs controls).

**MQ-D10. The deferred state's word.** **"Deferred" everywhere**, the owner's word of 2026-08-31;
`queuewaitinglist` ("Waiting list") is retired from the row (the band's "Deferred to the waiting list" goes
with the tile).

**MQ-D11. The key outcome in a row.** **A short status label** ("Key accepted" exists; add "Key: no seat",
"Key: no place", "Key: not confirmed") with the full sentence in its tooltip and on the review page.

**MQ-D12. Cards.** **Below a container width of 576 px**, with the four lines of 3.4.

**MQ-D13. Page size.** **50, as today**, core's paging.

**MQ-D14. The review page's Design System gaps (section 6).** **Fixed in the same stage as the queue's
look** (Q1), so the two decision surfaces change together.

**MQ-D15. A selection that spans methods (section 3.5.1).** (a) As today: one message for all, each
approval with its method's role and groups; (b) the sheet in sections by method, one message per method,
the note shared; (c) the footer refuses a selection that spans methods. **(b)**: (c) would make the
site-wide queue unusable for its commonest job, and (a) sends a student's wording to a future moderator.

**MQ-D16. Marking a deciding role.** **A "Grants a deciding role" warning label on the method's section of
the sheet, and on the row's method line on the spanning queues**, read from the role as C-D29 reads it; no
extra confirmation step beyond the sheet itself.

## 8. Stages

| Stage | Content | Tier |
|---|---|---|
| Q1 | The look: heading, band, status labels and short key outcomes, chips, the filter bar's look over today's controls, the grid (fixed header and Applicant column, picture, Review button, answers line, comment clamp), cards, empty states; the review page's gaps (section 6) | 1 to 2 |
| Q2 | The controls: status segmented control, the Filters panel (a `<details>` without JavaScript), the footer's three buttons, the decision sheet and the queue's cancel confirmation, the options above the grid without JavaScript, superseded refreshes, the sheet's sections for a selection across methods with one message per method (MQ-D15, MQ-D16); Behat on 5.3 (with and without JavaScript, the site-wide queue included) | 2 |
| Q3 | A look at a seeded queue (500 applications, 240 groups) on m503 and the mini cluster: render time of a page of 50, refresh time, the sheet with 50 selected | 1 |

Q1 can start at once; Q2 follows Q1. Neither waits on the audit report's stages; both read the same
tokens, so whichever lands first declares them in `styles.css` for the other.

## Sources read

- `manage.php`; `renderer.php` (`manage_page()`, `capture_table()` with `out(50, true)`);
  `templates/manage.mustache`, `manage_actions.mustache`, `queue_filters.mustache`, `queue_chip.mustache`,
  `queue_capacity.mustache`, `decision_controls.mustache`; `lang/en/enrol_apply.php` (`confirmusers`,
  `btnconfirm`, `btnwait`, `btncancel`, `queuereview`, `queuewaitinglist`, `keyoutcome*`).
- `CLAUDE.md`, the bullets on `?userenrol=`, the sticky footer, `checkbox_toggleall`, `profilelink`, the
  identity rule and the queue's group filter.
- `docs/design/applications-desk.html` ("Decided", 2026-08-31), `review-page-concepts.html`,
  `audit-report-study.md`, `audit-report-mockup.html`.
- The Design System: `INDEX.md`, `components/action-button.md`, `status-label.md`, and the parts already
  applied in the audit mockup (filter bar, segmented control, chip, counter, menu, filter panel, panel,
  skeleton, empty state).
- Measured live on m501 (instance 239) through the in-app browser, desktop and 375 px.
