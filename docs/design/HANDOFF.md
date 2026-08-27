# Handoff — read this before touching anything

State at the end of the session of 2026-08-27. **Everything is merged; nothing is in flight.**

- `master` at `aabbf0a`, working tree clean, no open pull requests.
- `version.php` is `2026082700`.
- **314/314 PHPUnit on m501 and m502**, Behat 4 scenarios, the full matrix audited leg by leg
  (7 legs, MariaDB and PHP 8.2 included) — every leg exactly 314 tests and 4 scenarios.

## What landed this session

| PR | What |
|---|---|
| [#36](https://github.com/uaiblaine/moodle-enrol_apply/pull/36) | **Previous/next navigation on the review page**, plus the redirect defect it uncovered. |
| [#38](https://github.com/uaiblaine/moodle-enrol_apply/pull/38) | **The submitted profile details on the review page.** |

That closes items 1 and 2 of the previous handoff. The implementation plan and its follow-on
list are now down to the smaller items below.

## Facts the PREVIOUS handoff stated that are now obsolete

Read this section before trusting anything else you remember about this plugin.

- **"Carry the scope as `id=`… `manage.php` already authorises that parameter" is FALSE**, and
  building on it would have let a request parameter choose which applications are enumerated.
  `manage.php` tests `userenrol` before `id` in an `else if`, so on the review path `$id` is read
  into a variable and then never authorised and never used. The scope is now DERIVED from the
  operator by `queue::scope()`. Do not reintroduce a scope parameter.
- **"table_sql stores the operator's choice in the `flextable_enrol_apply_manage_table` user
  preference" is FALSE.** `flexible_table::$persistent` defaults to false on both branches and
  this table never calls `is_persistent(true)`, so the sort AND the initials filter live in
  `$SESSION->flextable['enrol_apply_manage_table']`.
- **mod_book's skip-the-candidates-that-fail loop was deliberately NOT copied.** The previous
  handoff recommended it as "what a per-row gate needs". It is unnecessary here and would have
  been an unreachable guard: the three scopes are `can_manage_application()`'s three levels, so
  every application the walk reaches is decidable by construction. A test per scope holds it.
- **The initials-bar question is DECIDED: the walk does not honour it.** See
  `queue::neighbours()`'s docblock and `CLAUDE.md` for the three measurements behind it, including
  why turning the bar off would not have closed the gap.
- **"The full participants-page lock is DECIDED AGAINST" still stands** (2026-08-25). Unchanged.
- **The review page's snapshot panel does NOT read the live profile, and must never learn to.**
  The first version of it showed "what the profile says now" beside each submitted value, by
  handing the stored field key to `fields::current_value()`. That key comes out of `userinfodata`,
  which `restore_enrol_apply_plugin` writes **verbatim from a foreign archive**, and
  `current_value()` dereferences whatever `{user}` column an `s_` key names with no allowlist —
  the `DENY` list governs the WRITE path only. Measured on m502 with a crafted envelope: **the
  panel rendered the applicant's password hash**, to any reader for whom `visible_keys()` returns
  `ALL_FIELDS`, which is every teacher and manager. `test_the_panel_never_reads_the_live_profile_for_a_stored_key`
  holds it now. If a "versus profile now" comparison is ever wanted, it needs the key validated
  against the live field set first, and core's own `PROFILE_VISIBLE_*` gates applied on top.
- **Masking on the COURSE context costs a mentor the identity fields**, and that is an open
  product question rather than a settled one. They hold nothing in the course, so the panel gives
  them the name fields only. It is the stricter reading, and the one the report already took;
  `test_the_masking_is_judged_in_the_course_and_not_at_system_level` pins the context so a change
  of mind is a deliberate edit rather than a drift.

## What is left, in the order I would take it

### 1. The "Decide this application" action icon on the participants page

The cheapest remaining item, and the only one with a shape already worked out. `?userenrol=<ueid>`
is the natural target and lands on a page built for one decision. No core enrol plugin does this,
so there is no precedent to copy, and it needs a status gate or it renders on approved rows.

### 2. The audit recommendations

From `audit-trail-analysis.md`. The read-side half is done (#28), and the full participants-page
lock is decided against (2026-08-25, do not reopen without a new reason). The action icon above
was the third recommendation and is the only one left, which is why it has a section of its own -
an earlier version of this file repeated it here verbatim and made the list look longer than it is.

### 3. Smaller things, all measured and all still open

Each one re-measured against `master` at `73f116b` on 2026-08-27, by grep rather than by memory:
`add_instance_groups()` still declares the default and still has one caller; `decidedgroups` and
`decidedrole` appear **zero** times in either backup class; all three columns appear in
`provider.php` only inside the metadata declaration, never in `export_submissions()`'s object; and
`timestart` appears nowhere in `manage.php`, `classes/bulk/` or `renderer.php`. An earlier version
of this line claimed a re-verification against the previous master that had not been done.

- **`decidedgroups` and `decidedrole` are not carried by backup/restore.** Group and role ids are
  course- and site-local, so it needs `get_mappingid()`, and a restore of an older archive needs
  `?? 0` on the read — `restore_enrol_apply_plugin` casts the parsed chunk to an object and every
  current read is bare, which is an `E_WARNING` under `--fail-on-warning` the moment an element is
  added after the fact.
- **`outcomemessage`, `decidedgroups` and `decidedrole` are declared in the privacy metadata and
  exported nowhere.** Decide which way it should go.
- **The enrolment-period branches of `confirm_enrolment()` are still unreachable from the UI.**
  Neither `manage.php`, nor the bulk decision form, nor the review page supplies
  `timestart`/`timeend`. The only caller that does is a unit test, whose green makes the branch
  look exercised. Either finish it or delete it.
- **`add_instance_groups()`'s `int $userenrolmentid = 0` default is dead** (`lib.php`) — one
  caller, always passing the id. Same species as the queue table's removed parameter.
- **`chosen_groups()`'s empty-array branch cannot be consumed** (`classes/local/submission.php`).
- **A core defect, not fixable from here:** the participants page renders a waiting-list row
  (`ENROL_APPLY_USER_WAIT = 2`) as a green **"Active"** badge.
- **A bulk decision reaches ONE apply instance per course.** Not fixable from here.
- **If the full history is ever wanted**, the shape is a decision-log table, not more status values.

## How to work here

Unchanged unless marked NEW. The NEW ones all cost real time this session.

- **Mutation-check every guard**, and **count the tests, not the failures**. A version bump stales
  the PHPUnit environment — re-initialise after every bump, before the mutations.
- **Make the harness print the `OK (N tests, …)` line, not only `Tests:`.** PHPUnit prints
  `Tests:` on failure and `OK (…)` on success.
- **A mutation that reddens nothing is a finding.** It happened twice this session and was right
  both times: renaming `render_application_navigation()` reddened nothing because
  `renderer_base::render()` resolves the template from the CLASS NAME, so the method was deleted
  and the wrong prose with it.
- **NEW — never give a subagent write access to the working tree.** An adversarial reviewer
  deleted a production guard from `queue.php` to test a finding and did not restore it. The suite
  was green at the time because no test held that guard, so it sat in the tree unnoticed until a
  later test caught it. Have reviewers work read-only, or in a copy. Scratch test files they leave
  behind (`tests/**/zz*_test.php`) also contaminate any matrix leg that starts afterwards — one
  leg ran 304 tests instead of 303 that way.
- **NEW — the mutation harness must abort loudly when its restore fails, and only one run may be
  in flight.** A stray file in the pristine directory made `restore` raise a `KeyError` that the
  runner ignored, so mutations STACKED and three results were measured against the wrong code; and
  two overlapping runs produced a report where one mutation "reddened nothing" purely because the
  other run had restored the tree underneath it. Both are fixed in the scratchpad harness (hard
  exit on restore failure, lock file), but rebuild those guards if you write a new one.
- **NEW — an adversarial pass can die on the weekly subagent limit mid-flight, and findings whose
  verifiers all died show as DISMISSED with `votes=0/0`.** They are UNJUDGED, not refuted. Ten of
  them were unjudged in the first pass and several were real, including two HIGHs that no test
  held. Read the vote count, never the bucket.
- **NEW — run the adversarial pass. The second one found a HIGH-severity disclosure in code that
  was already green, mutation-checked, matrix-clean and verified on the live site.** Twenty
  confirmed findings over one small slice. The defect was mine and the shape is worth
  remembering: a feature added *beyond the brief* (a "profile now" comparison nobody asked for)
  was the entire attack surface, and the comment justifying it — "only for a row already judged
  visible, so the live value cannot widen what the masking allowed" — was false exactly where the
  exposure was. **When a slice grows a capability the plan did not ask for, that is the part to
  attack first.**
- **The adversarial pass's VERIFIERS can be wrong, and being wrong is the expensive direction.**
  This session a finding dismissed 1/1 ("the empty-mentee guard is held by no test") was correct.
  **Measure any dismissed finding whose subject is a claim you wrote yourself.**
- **NEW — never build PHP source with a non-raw Python string.** `\core_user\fields` becomes
  `\core_user` + a FORM FEED, and the failure surfaces as `Class "…" not found` from the
  autoloader across 54 unrelated tests rather than as a syntax error at the site of the damage.
  Use raw strings or quoted heredocs, and scan for control characters after generating a file.
- **The most expensive defect here is a confident wrong sentence.** This session produced five,
  every one of them load-bearing: that `can_access_course()` with a capability was "the pair
  `manage.php?id=` demands" (it is not — `$onlyactive` defaults to false, so a suspended or
  expired enrolment passes); that a base `render_` method was what made a renderable
  theme-overridable; that "only `get_initial_first()` consults `use_initials`"; that the no-queue
  scope needed a hidden course; and that LEFT joins "cannot change which rows exist". All five now
  say what was measured and say that an earlier draft claimed more.
- **Never restore a mutation with `git checkout <file>`.** Copy to the scratchpad first.
- `git worktree prune` before every test run. Never edit the tree while the matrix runs. Read the
  per-leg logs rather than the summary line, with anchored patterns (`^-- [a-z]+: FAILED$`) and
  check every leg's TEST COUNT. One PR per unit of review, and `--repo uaiblaine/moodle-enrol_apply`
  on every `gh` call. Never pass `--delete-branch` when another PR is stacked on that branch.
- **Nothing in this repository executes the plugin's JavaScript** except the `@javascript` Behat
  scenarios, and **nothing renders CSS** — the RTL chevron rule added this session is verified by
  reading the cascade, and says so, exactly like the sticky-footer polyfill.
