# Mutation spec

Every guard in this plugin is paired with the test that must go red when the guard is
removed. Run the whole set with:

```sh
mdl mutate moodle-enrol_apply mutations/gates.conf --dry-run   # patterns only, seconds
mdl mutate moodle-enrol_apply mutations/gates.conf             # the sweep, ~4 min per line
mdl mutate moodle-enrol_apply mutations/gates.conf --only B_icon_capability
```

`mdl mutate` lives in `~/dev/moodle-dev/bin/mdl-mutate`; its own header documents the
guards it carries and why each exists.

## Why this is versioned

A green suite says the tests pass. It does not say the tests would *notice* if a guard
were deleted, and several tests in this repository have passed against the exact mutation
they were written to catch. The pairing in `gates.conf` — this guard, that test — is a
claim, and a claim that lives only in a commit message or a docblock cannot be re-checked.
Here it is executable.

They are not a sample. Each one was a real decision that a reviewer questioned or a
defect that shipped:

- **A, B, E, F, G** hold the participants-page decision icon. `G` is the sharpest: it
  widens the capability gate to `can_manage_application()`, the alternative
  `get_user_enrolment_actions()`'s docblock argues against at length. Before
  `test_a_mentor_is_offered_no_icon_in_the_course` existed, that mutation reddened
  **nothing** — four independent review lenses flagged the gate as unpinned, and they were
  right.
- **C and D** are the reason the "awaiting a decision" predicate was extracted into
  `queue::is_awaiting_decision()`. Deleting either half reddens a **bulk** test and an
  **icon** test together, which is what proves the two readers share one definition rather
  than two that happen to agree today. That is the property the extraction bought, and it
  is the one thing a future refactor could quietly lose.
- **H** restores an empty-array return that reached `get_in_or_equal()` and threw. It was
  filed as unreachable dead code for weeks; a restore is the route that reaches it.
- **I** removes the file-scope requires that make this plugin's two `CoversClass` targets
  resolvable. Without them `mdl ci --coverage` fails, and which tests warn depends on
  execution order.
- **Q and R** are the write door's eligibility check, and they are a pair because the guard
  has two independent ways to be wrong. `Q` deletes it, restoring the state in which
  `allow_apply()` guarded the two screens that OFFER an application and not the method that
  writes one. `R` is the sharper of the two: it keeps the call and drops the applicant's user
  id, so the cohort clause is judged against whoever is logged in. Only the cohort test can
  see `R` at all — the other three restrictions ask nothing about a person — which is why that
  test deliberately puts the operator inside the cohort and the applicant outside it. A test
  written the obvious way, with both of them outside, passes against `R`.

## Rules for adding one

- **Add the mutation in the same change as the guard.** A guard added without one is a
  comment, not a boundary.
- **Name the test that must redden**, in a comment under the line. If you cannot name one,
  the guard is unheld and that is the finding.
- **Run `--dry-run` after any refactor that moves these files.** A pattern that no longer
  matches is a hard error rather than a silent pass, but you want to learn that in seconds
  rather than in the middle of a sweep.
- **Perl interpolates `$variables` on both sides of `s///`, and `\Q...\E` does not stop
  it.** A pattern naming `$manager` matches nothing; a replacement naming `$row` writes
  nothing. Both happened here, in opposite directions, in one afternoon.
- **Anchor on what makes the line unique.** `lib.php` contains the same
  `has_capability('enrol/apply:manageapplications', $manager->get_context())` twice — in
  `get_bulk_operations()` and in `get_user_enrolment_actions()` — and only the
  `return $actions;` that follows tells them apart.

## What this directory is not

It is not shipped: `.gitattributes` marks it `export-ignore`, so `git archive` leaves it
out of the release zip, exactly like `docs/`. It is not run by CI either — GitHub runs the
suite, not the mutations. This is a local gate you run when you touch a guard.
