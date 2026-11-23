# The site-wide and mentee queues on the data report: plan

Stage 6 of `confirm-queue-as-report-plan.md`, opened by the owner on 2026-11-23. The queue of one method
(`manage.php?id=<method>`) is a grid since 2026112304; the two queues that span courses (`manage.php` with no
`id`) still draw the server-rendered table. This plan brings both to the grid. The approved picture is
`site-queue-as-report-mockup.html` (the owner: "está fiel, planeje"); where this plan departs from a note in
it, section 3 says so.

Decisions are numbered SQ-D. Stages are numbered W. The owner took the recommended answer of every open
decision on 2026-11-23 and asked for the work to go ahead.

## 1. What the survey of the code settles

- **Three readers share the id-less page, and the request never picks among them.** `queue::listing_scope(0)`
  answers, in this order: a holder of the deciding capability in the system context (the site-wide queue,
  deciding), a mentor (the applications of their mentees, deciding), a holder of the viewing capability in the
  system context (the site-wide queue, read only), nobody else. The identity context is the system for the
  site-wide readers and null for a mentor.
- **The table already knows the wide scopes; the engine and the services do not.** `applications::for_scope(0)`
  works: the course column, the method under it with its "grants a deciding role" label, the group column
  (site-wide only), the answers as one masked "Details submitted" cell (site-wide only), the generic comment
  heading, a course and a category filter (site-wide only). `queueengine` refuses id 0 at `locate()`,
  dereferences the instance in `catalogue()` and `questions()`, drops `course` and `category` from the filters
  it passes on, sorts by nothing that names a course, and returns no course or method in a row. The three
  services validate a course context.
- **What the wide table does not have today**: a group filter, the "how the group was named" filter and the
  "applied before" label all need one instance. Counts on the course filter do not exist; its list is every
  course with an apply method, unbounded.
- **A selection across methods is decided method by method, and nobody chooses groups or a role there.**
  `decision::groups()` buckets the posted ids by the method `{user_enrolments}` says, a method without its own
  `outcomemessages[<enrolid>]` takes the plain message, and `apply()` runs once per bucket. With no instance
  the page offers no group or role chooser: the dialog says "Each approval uses its own method's role and
  groups". The grid's client sends one message and no `outcomemessages[]`.
- **Capacity is read one method at a time**: `capacity::state()` runs at most two counts per instance and has
  no batched form.
- **The data report's View menu is closed**: three hard-coded choices; a caller can only take a subset. The
  filter panel draws a facet of more than eight options as a list searched on the client over what the server
  sent, showing fifty.
- **One column preference serves every queue** (`enrol_apply_queue_grid`), intersected with the reader's
  catalogue on each read.
- **Cost that grows with the page's courses**: the profile links are judged per distinct course of the page,
  and the answers' mask reads once per distinct course. On a site where a plugin implements
  `control_view_profile` the profile rule is core's, user by user (measured in #181: two statements a row).
- **The predicate cannot use an index on the status**: `{user_enrolments}` is indexed on `enrolid` and
  `userid` only, so the site-wide queue reads every enrolment of every apply method and filters. Today's table
  does the same.
- **Size on m503**: 3,639 applications awaiting in 7 methods of 6 courses.

## 2. Decisions

| Id | Decision | State |
|---|---|---|
| SQ-D1 | The same engine and the same three services serve the wide queues: `enrolid` 0 means "the wide queue `listing_scope(0)` gives this reader". Which of the three it is never comes from the request | owner, 2026-11-23 |
| SQ-D2 | "Course · method" is the second column, after the applicant: the course (a link, as the table's), the method under it with the labels the table prints (grants a deciding role; full). Sortable by course name | mockup |
| SQ-D3 | One "Details submitted" column for the answers, masked in each row's own course as today, not sortable and not searched. No column per answer in a wide queue | mockup |
| SQ-D4 | Filters of the site-wide queue: Course (counted, most applications first, searched on the client), Enrolment method (offered once a course with more than one method is chosen), Waited more than, Group (with or without a group), and the scope. In the address under the table's names where one exists (`course`), `method` and `grouped` for the new ones | mockup |
| SQ-D5 | The Category filter the table has today stays, as a counted facet of the categories that hold a course with applications. The mockup does not draw it | owner, 2026-11-23: kept, so nothing a reader has today is lost |
| SQ-D6 | The course facet sends at most 200 courses and says how many it left out; the bar's search already finds a course by its name. A search on the server for the facet waits for a site that needs it | owner, 2026-11-23 |
| SQ-D7 | Top tiles of the site-wide queue: awaiting (oldest, over seven days), deferred, courses and methods with applications, methods at their limit. They do not follow the filters | mockup |
| SQ-D8 | "Methods at their limit" and the "Full" label come from a batched form of `capacity::state()` over the methods in the reader's scope, computed on each request, never cached. Measured before the client is built; if a site-wide read costs more than the page itself, the tile and the label go behind the "By course" view only | owner, 2026-11-23: decided by the measurement of W1 |
| SQ-D9 | A fourth view, "By course": one row per method with what awaits, what is deferred, the oldest wait, the places taken and whether it is open, and a link to that method's queue. No selection. It follows the scope and the filters. Site-wide readers only | mockup |
| SQ-D10 | A decision over several methods is today's form POST with one `outcomemessages[<enrolid>]` per method and one note. The wide queue offers no group or role chooser, as today: each approval uses its method's own. The mockup's note says choosers appear when the whole selection is one method; that would be new behaviour and a second door onto each method's controls | owner, 2026-11-23: today's rule stays; the dialog links to the method's queue for whoever wants to choose |
| SQ-D11 | The wide queues keep a column preference of their own (`enrol_apply_queue_grid_wide`), declared and exported with the user's data: their columns are not the method queue's | owner, 2026-11-23 |
| SQ-D12 | The grid becomes the page of the wide queues as it is of the method queue: `view=table` and no JavaScript keep the table | mockup |
| SQ-D13 | The mentee queue takes the same grid in the same line of work, in a pull request of its own: applicant, course and method, wait, comment. No identity line, no "Details submitted", no group column, no course filter, no "By course", and only the first two tiles, all as the table has it today | owner, 2026-11-23 |
| SQ-D14 | "Applied before" stays absent from the wide queues (it needs one method today) | settled by the existing rule |
| SQ-D15 | Page sizes 30, 50, 100; 50 by default | as the method queue |

## 3. Where the plan departs from the mockup

- **A Category facet** (SQ-D5) is in the plan and not in the picture.
- **No group or role chooser on a wide queue** (SQ-D10), where the mockup's note offers them for a selection
  within one method.
- **The mentee queue's title** stays the page's own ("Confirm enrolments"); the mockup wrote "Your mentees'
  applications". A new title is a string and a decision the owner has not been asked.
- **The Group facet** is "with a group, without a group", as drawn; it is new for the site-wide queue, which
  has no group filter today, and it is not the list of groups (they belong to each course).

## 4. Security design of W1

The effect: a reader lists the applications awaiting a decision across the site, or those of their mentees,
with the applicants' identity values and answers the table already shows them, and keeps a column choice of
their own. Nothing is decided through these doors.

1. **Who may call it.** A logged-in user who is not a guest and is one of the three readers of
   `listing_scope(0)`; the services are `ajax` only. The same rows are already reached by `manage.php`, the
   review page and the course report, each with its own gate, unchanged.
2. **Which object decides the context.** There is none for id 0: the context is the system's, by its level,
   and the capability question is `listing_scope(0)`'s. A posted course, category or method only narrows
   within the scope; a method must be an apply instance; none of them moves the context.
3. **Which facts the decision reads.** The scope on every request, never a stored one; the mask of each row
   in that row's own course; the identity fields in the system context for the site-wide readers and none
   for a mentor; the capacity as it stands.
4. **Other doors onto the same rows.** Every reader of the wide scopes goes through `listing_scope()`
   (`manage.php`, `queue::scope()`, the engine, `return_url()`); every reader of the engine calls
   `require_listing()` itself, the new ones included.
5. **What the client may post.** The id (0), a scope from its vocabulary, the search, filters by declared
   name cleaned per reader, a page, a page size from its vocabulary, a sort the catalogue offers; for the
   preference, keys and widths cleaned for shape and intersected with the catalogue on every read.
6. **The interface's sequence.** The shell's props, then the summary, then the rows, then a preference write
   followed by the rows. Each call stands alone and keeps no state between them.
7. **What is created.** One user preference, declared and exported by the privacy provider and removed by
   core with the user.
8. **Where a refusal happens.** Before anything is read: the id, the context, the guest check, the listing.
   It writes nothing.

## 5. Stages

Each stage is one pull request against `MOODLE_503_STABLE`.

### W1: the headless wide queue (tier 3: SQL, web services, privacy)

- `queueengine`: `locate(0)` gives the system context and no instance; every reader keeps refusing through
  `require_listing()`. The catalogue of a wide scope is the table's: applicant (fixed), course and method,
  wait, group and details (site-wide only), comment (the generic heading). Sort by course name. The row gains
  the course (id, name, link as `col_course()` builds it), the method (id, name, the deciding-role and full
  labels, its queue's address only for a reader who may open it) and the details as the table's
  `visible_entries()` gives them.
- Filters: `course` and `category` reach the table in the site-wide scope and nowhere else; `method` (an apply
  instance, checked) and `grouped` are new clauses of the table, so the server-rendered page takes them too.
  Facets with counts for course, category, method, waited and grouped, each count what choosing the option
  would list; the course facet bounded by SQ-D6.
- `capacity::states(array $instances)`: the batched twin of `state()`, in the same file, with a test that it
  equals `state()` instance by instance, limits and dates included.
- `enrol_apply_queue_summary` with `enrolid` 0: awaiting, the oldest wait, the late ones, deferred, courses
  and methods with applications, methods at their limit (site-wide readers); the first four for a mentor.
- `queuepreference` for the wide catalogue (SQ-D11), with its privacy declaration and export.
- Services: the context is the system's for id 0, the guest check and the engine's refusal as they are.
  Returns stay allowlists.
- Tests: the engine's rows equal the table's, in set and order, for a site-wide decider, a site-wide viewer
  and a mentor; a mentor's answer carries no identity value and no details in the catalogue, the filters, the
  sort keys or the rows; the details are masked in each row's course (two courses, a field one of them
  withholds); a forged `course`, `category` or `method` is ignored or refused as the table does; an ordinary
  teacher asking for id 0 is refused; each facet's count is what choosing it lists. Mutation gates per guard.
- `security-diff-read` on the diff. An MDL Shield review if the owner asks.
- Measured before any client work, on m503 and the mini cluster, with a seed of many courses added to
  `moodle-dev/data/enrol-apply-demo` (a page whose fifty rows come from fifty courses is the worst case): the
  page, the facets, the summary with and without the capacity batch.

### W2: the shared parts take a caller's view (tier 2)

- `js/esm/src/datareport`: the View menu takes extra entries from its caller; a cell kind for two stacked
  links with labels (the course and its method). The course report and the method queue do not change;
  `tsc --noEmit` and their Behat hold that.

### W3: the site-wide list, read and decide (tier 3: it drives the decision route)

- `queue_view` and `QueueView` without an instance: the props, the tiles of SQ-D7, the column of SQ-D2, the
  details, the facets, the address parameters, the return address after a decision (`return_url(0, ...)`).
- The dialog for a selection over several methods: one section per method with its message, the note, the
  spans sentence and the full-method warning; `decide.ts` posts `outcomemessages[<enrolid>]`. The row carries
  what the table's `data-enrolid`, `data-methodname`, `data-coursename` and `data-deciding` carry today.
- `queue_view::wanted()` admits the site-wide scope (SQ-D12).
- Behat: the scenario "A selection across enrolment methods is decided method by method" on the grid; the
  course and method filters; a site-wide viewer sees no selection; the table under `view=table`.
- PHPUnit: a decision posted as the grid posts it over two methods writes what the table's post writes.

### W4: the "By course" view (tier 3: SQL, a web service)

- `enrol_apply_queue_methods`: one row per method in the reader's site-wide scope after the scope and the
  filters, with the counts, the oldest wait and the capacity of `capacity::states()`; paged, sorted by what
  awaits by default. Refused for a mentor.
- The view in the client, with no selection and no dock.

### W5: the mentee queue (tier 3)

- `wanted()` admits the mentor scope; the catalogue, the tiles and the filters of SQ-D13.
- Tests in a request, not only under PHPUnit: a Behat scenario as a mentor (the plugin has none today) showing
  the grid, no identity line, a decision.

### W6: finish (tiers 1 and 0)

- Design System: the data report's caller-defined view, the cross-object column and the dependent facet (new
  D numbers from D136, agreed with the session working on that component).
- README with a screenshot of the site-wide queue, CHANGELOG, the plugin's `CLAUDE.md` (the rules of the wide
  scopes replace "the site-wide and mentee queues keep the table").
- A browser pass on m503 and the mini cluster: site-wide decider, site-wide viewer, mentor; narrow width;
  dark mode.

## 6. Risks

- **A wider door onto answers and identity.** The site-wide grid shows what the site-wide table shows the same
  reader; the mentee grid shows less than the site-wide one by a rule that is easy to lose when two scopes
  share one code path. Held by the per-scope tests of W1 and by a mutation gate on each branch.
- **A site-wide read on every page.** The awaiting predicate filters every enrolment of every apply method,
  and the capacity batch adds two grouped counts. Nothing is cached, by the cluster rule (a cached figure
  would be stale on the node that did not write it, and a wrong "Full" misleads a decision). SQ-D8 decides on
  the measurement.
- **Statements per course of the page.** Linear in the page's distinct courses, and per row where a plugin
  vetoes profiles. Measured in W1; a bound, if needed, is a batch across courses in `profilelink`.
- **The course facet on a site with thousands of courses.** Bounded by SQ-D6.
- **Two readers of one queue drifting**, now in three scopes. The engine-versus-table test runs per scope.
- **The mentee list** is resolved on every read of the scope and is unbounded in the mentor's role
  assignments; the grid adds two service calls a page to today's one read. Not cached (existing rule);
  measured with a mentor of many mentees in W5.

## 7. Order and size

W1 first; W2 can run beside it. Then W3, W4, W5 in that order, W6 last. W1, W3, W4 and W5 ask for the matrix,
a security read and a mutation sweep; W2 for one leg with Behat.
