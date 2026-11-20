# Applicant group choice, and a redrawn instance form

Status: **decided 2026-11-19** (see "Decisions taken" below; C-D28 and C-D29 were raised and decided the same day). G2 merged in #145 (2026111900), G1 in #147, G4 in #148 (2026112000), G3 in this change (2026112001); G5, G6 and T1 follow.
Decisions C-D1&ndash;C-D27 below each keep the recommendation they were decided on. Interactive mockup:
[`applicant-group-choice-mockup.html`](applicant-group-choice-mockup.html) (open in a browser; its
decision switches change every screen).
Builds on [`group-enrolment-key.md`](group-enrolment-key.md),
[`group-key-only.md`](group-key-only.md) and [`group-review-scope.md`](group-review-scope.md),
whose decisions stand unless this file says otherwise.

## The request

The owner's words, kept verbatim:

> Para o plugin moodle-enrol_apply, quero um novo modo de inscrição com seleção de grupo ao
> candidatar-se. A solicitar uma inscrição, no modal apresenta um seletor dropdown com
> autocomplete com os nomes dos grupos para o usuário escolher. Os grupos que aparecem devem ser
> definidos nas configurações do método de inscrição, algo como na interface do
> moodle-mod_choicegroup ao configurar os grupos da atividade. Investigue a viabilidade, faça um
> brainstorm de design, pois com tantas opções no método a tela de configuração da instância
> requer uma repaginada, melhorando a hierarquia da informação, com novos botões de ajuda para
> complementar explicações sobre o que cada campo faz (hoje alguns estão ausentes).

Two pieces of work, separable: **(1)** the applicant picks a group from a list the teacher
defines; **(2)** the instance form gets a hierarchy and the help it lacks. (2) does not need (1)
and can ship first (stage G1).

## Verdict: feasible, and most of the machinery already exists

| Need | What already exists | What is new |
|---|---|---|
| A group attached to a pending application | `enrol_apply_submission.keygroupid`, read by the scope, the queue column and filter, the review chip, the report and backup | a column for a *chosen* group, and one helper that says "the application's group" (C-D4) |
| Decided by the right people | `groupscope`: in separate groups, an application reaches the teachers of its group | nothing: a chosen group narrows exactly as a key group does |
| Seats per group | `groupseats` over `local_groupdist`, optional | the chooser reads it (C-D8) |
| Joined on approval | `add_instance_groups()`, `decidedgroups`, `joinedgroups`, the review page's group chooser | the chosen group pre-ticked in that chooser (C-D10) |
| Group names shown only where core shows them | `groupvisibility::visible_to()` | the offer filtered by it, with the applicant as reader (C-D7) |
| A searchable dropdown in the modal | `application_form` is a `dynamic_form`; formslib's `autocomplete` element | a spike proves it in the modal on 5.1&ndash;5.3 and on `apply.php` without JavaScript (G0) |

The cost sits in three places: the schema (a column on the record, a column on
`enrol_apply_groups`, an instance setting, an upgrade step, backup and restore, privacy), the
roughly twenty production files that read `keygroupid` today (six of them build SQL with it), and
the instance form, which is the second half of the request anyway. It is a **tier 3** change
(SQL, `install.xml`, upgrade, privacy).

### How choicegroup does it, and what to take from it

`mod_choicegroup`'s form builds its group picker out of raw `html` elements: a multi-select of
the course's groups grouped by grouping, an "add" and "remove" arrow pair, a second select of the
chosen ones, a per-group limit stored in hidden fields and a "apply to all groups" button. Its
strengths are the grouping view (pick a whole grouping at once) and the per-group limit beside the
name. Its weaknesses are the ones the fleet rules forbid: raw markup in a moodleform, JavaScript
moving values between selects, no keyboard path, nothing on a phone.

Take the two ideas, not the widget: an `autocomplete` (multiple) over the course's groups, plus a
**"Add the groups of a grouping"** select that copies a grouping's groups into it (C-D3). Seats are
not configured here: they already belong to `local_groupdist`'s group custom field, and the form
only shows them.

## The applicant's side

The modal gets one new element, placed after the description and before the comment, because it is
the first thing the applicant decides and the comment may refer to it:

- **Label:** "Group" (or the teacher's own prompt, C-D12), required or optional per C-D9.
- **Widget:** a searchable list (C-D23): opened, it shows up to eight groups that still have
  places, and says how many more there are ("Showing 8 of 15 groups with places. Type to search
  all 22."); typing searches every offered group, those with places first, then the full ones,
  disabled. Each row carries a status label, "7 places left" or "Full", when seats are known.
  Without JavaScript (`apply.php`) it is the plain `select` it enhances, in the same order, the full
  groups disabled.
- **Full groups** are reachable by typing, disabled, with "Full" beside the name (C-D8), so the
  applicant learns why their group cannot be chosen rather than thinking it does not exist.
- **After submission** the acknowledgement and `applicantstate` name the chosen group: "Your
  application for the Evening cohort is waiting for a decision."

Server side, `submit_application()` re-resolves the posted group inside its lock: it must be in the
instance's offer, still exist, still be visible to the applicant, and have a seat when seats are
known. Anything else is a validation error on the element, never a silent drop to "no group".

## The decider's side

- The review page's chip reads "Chose: Evening cohort" (a key reads "Key: Evening cohort" as now).
- The group chooser on the review page and in the bulk form pre-ticks the chosen group on top of the
  instance's defaults; the decider may untick it or pick another (C-D10). What they record is
  `decidedgroups`, as today.
- The queue's group column and filter, the report's group column and filter and the separate-groups
  scope all read "the application's group" through one helper (C-D4), so a chosen group behaves
  exactly like a key group there.
- The approval notice names the group joined, which it already does through `joinedgroups`.

## Decisions

Each line: the question, the options, the recommendation in bold.

**C-D1. Where the mode is stored.** ~~`customchar3`~~ **Changed in G2 (2026-11-19):** in
`enrol_apply_options.groupchoice` (0 off, 1 optional, 2 required), beside the other per-method
options, so every new option has one home and one strict reader (`\enrol_apply\local\instanceoptions`).
Read strictly: a value outside the vocabulary reads as off.

**C-D2. Where the offer (the list of groups) is stored.** (a) A `purpose` column on
`enrol_apply_groups`, or (b) a new table `enrol_apply_offer`. ~~(a)~~ **Changed in G2 to (b), for
security:** six readers of `enrol_apply_groups` take every row as a group the approval joins, and
`edit.php` deletes and rewrites them all; with (a), one reader that forgot the filter would put every
applicant in every offered group. A table of its own cannot be misread that way.

**C-D3. How the teacher builds the offer.** (a) A hand-picked list, with an "Add the groups of a
grouping" shortcut that copies them in; (b) a live grouping, so a group created later is offered
at once; (c) both. **(a) for now.** A live grouping is better for large courses, but it makes the
offer change with no edit of the method, which the audit trail does not record. Keep (b) for later
if the owner wants it.

**C-D4. Where the choice is recorded.** (a) Reuse `keygroupid` with a new `keyoutcome`; (b) a new
column `chosengroupid`, and one SQL helper (`submission::application_group_sql()`) that reads
`keygroupid` when it is set and `chosengroupid` otherwise. **(b)**: `keyoutcome` is "what the key
did", and a choice is not a key; the audit trail must still tell "proved membership with a key" from
"asked for a group". Since C-D5 never lets both be set on one application, the helper is a
`CASE`, and the six SQL readers (`groupscope`, `groupfilter`, `queue`, `table/applications`,
the report entity and filter) switch to it in one change, each pinned by a test with a chosen group.

**C-D5. Living with group keys.** (a) Choice only while keys are off; (b) also with "a valid key
enrols at once; without one, the application is reviewed" (`'any'`): a key holder enrols at
once, everybody else picks a group and waits; (c) with every key mode. **(b).** In the two
key-only modes the key decides the group, so a chooser there could only contradict it. In `'any'`
the chooser is shown, and a valid key wins over a chosen group (the key is stronger evidence), as a
ticked `withoutkey` wins over a posted key today. The instance form refuses saving choice with a
key-only mode.

**C-D6. Group choice in the form's hierarchy.** The two settings become one question on the form,
"How does an applicant's group get decided?", with three answers: *A decider chooses on approval*
(today's default), *The applicant chooses from a list*, *The applicant types a group enrolment key*;
the key's four modes and the choice's two become the second level. Storage stays two columns (no
value of `customchar1` changes, no upgrade step rewrites them); the form maps the answer to both
on save. **Recommended**: it is the one place the request's "hierarchy" matters most, because
today two independent selects can describe a combination nobody meant.

**C-D7. Which groups an applicant may be offered.** Only groups `groupvisibility::visible_to()`
lets the applicant see, which for a non-member is `GROUPS_VISIBILITY_ALL` alone. **Filter, and
say so on the instance form**: a group "visible to members only" in the offer is shown there with
"never offered: hidden from non-members", rather than refused, so the list survives a visibility
change. Deleted groups drop out by the join; there is no `group_deleted` observer today and none is
needed.

**C-D8. Full groups.** (a) Listed, disabled, "full"; (b) hidden; (c) a per-instance switch. **(a)**,
with (c) left for later. A pending application takes no seat (the rule `'review'` key mode already
follows), so a choice reserves nothing; the seat is re-checked at submission (refused if full) and again at approval, where the
existing overfilled warning applies. Without `local_groupdist` no group is ever full.

**C-D9. Required or optional.** `'required'`: no submission without a group. `'optional'`: a
"No preference" entry, recorded as `chosengroupid = 0`. **Both modes**, because a programme that
lets the coordinator place undecided applicants needs the second.

**C-D10. What approval does with the choice.** **The chosen group is pre-ticked in the decider's
chooser, on top of the instance's defaults; the decider may change it.** An approval with no
recorded group choice (the participants page's "Edit enrolment", a bulk approval with an empty
choice) joins the chosen group plus the defaults, as a key group does in `'review'` mode today
(`add_instance_groups()`). Whether the chosen group may be *overridden* is the owner's call: the
alternative is "binding", where the decider can only approve or refuse.

**C-D11. Separate groups.** An application to group X reaches X's non-editing teachers through
`groupscope`, as a key application does. **Keep it**: it is what makes the feature useful at scale
(each group's tutor reviews their own applicants). The instance form's existing warning about
keyless applications in a separate-groups course gains the case "No preference", which reaches only
those who see every group.

**C-D12. The chooser's label.** A fixed "Group", or a teacher-written prompt beside it (as the
comment has `customtext2`). **Fixed for now**: no free text column is left without a schema change,
and "Group" is what the applicant needs. If the owner wants a prompt, it goes in the offer table's
configuration, not in a `custom*` column.

**C-D13. Changing the choice while pending.** Out of scope: an applicant who wants another group
withdraws and applies again, which the durable record already traces.

**C-D14. Many groups.** The applicant's list is C-D23&ndash;C-D24. On the instance form, above
`groupfilter::THRESHOLD` the offer's list folds as the keyed groups already fold (GF1&ndash;GF4).

### The instance form

**C-D15. Seven sections, each a `header`** (the fleet rule: sections are headers, never rows moved by
JavaScript). Today the form is one header and about twenty-five rows, and the code comment above
`customchar1` explains why no header was added: one opened mid-form stays open to the end. Opening
*every* section with its own header removes that trap.

| # | Section (expanded by default?) | Fields |
|---|---|---|
| 1 | **General** (yes) | instance name, allow existing enrolments, accept new applications, role on approval |
| 2 | **When and how many** (yes) | applications open, applications close, maximum applicants, places, places taken (static) |
| 3 | **Who may apply** (no, unless set) | cohort |
| 4 | **Application form** (yes) | text shown to applicants, ask for a comment, comment prompt, requested profile fields, the questions (a summary line, "3 questions, 1 conditional", and the existing "Save and edit questions" button) |
| 5 | **Groups** (yes when a mode is on) | "How does an applicant's group get decided?" (C-D6), then per answer: the offer list, required/optional, the key mode, keyed groups summary, warnings; always: groups joined on approval |
| 6 | **After approval** (no) | enrolment duration, notify before expiry, notification threshold, save profile changes for future applications |
| 7 | **Notifications** (no) | notify of new applications, managers' notice of key admissions |

Section 4 replaces today's separate "Requested fields" header. A collapsed section that holds a
non-default value opens on load (`setExpanded` from the instance's values), so nothing configured
hides behind a fold.

**C-D16. A summary strip above the sections.** One line built from the saved instance: "Accepting
applications · reviewed by a decider · 4 of 30 places taken · applicants choose among 6 groups".
**Recommended, cheap**: every fact already has a reader (`capacity`, `groupkey`, the new mode).

**C-D17. Labels that say what they do** (en; pt_br in lockstep, "inscrição" never "matrícula"):

| Element | Today | Proposed |
|---|---|---|
| `status` | Allow Course enrol confirmation | Allow existing enrolments (core's `enrol_self` wording) |
| `customint6` | Allow new course enrol request | Accept new applications |
| `roleid` | Default role | Role on approval |
| `groupselect` | Group assignment | Groups joined on approval |
| `customtext1` | Textarea description | Text shown to applicants |
| `customint7` | Commentary field | Ask for a comment |
| `customtext2` | Custom label | Comment prompt |
| `currentoccupancy` | Places (a second row with the same label as `customint4`) | Places taken |
| `notify` | Define who gets notified about new enrolment applications. (a sentence as a label) | Notify of new applications |

**C-D18. Help buttons.** Missing today, to add: `status`, `customint6`, `roleid` (a decider may
pick another role, the stamp and why), `customtext1` (where it shows, that it is HTML),
`notify`, and the new `groupchoice`, `groupoffer`, `groupchoicerequired`. Each new section header
gets a short help too, as `requestedfieldsheader` already has. Existing helps are kept and reread
against the new labels.

**C-D22. The application window's dates stay core's `date_time_selector`**, the element the course
settings use for the course start and end dates: "Enable", then day, month, year, hour and minute,
then the calendar button. The form already uses it (`enrolstartdate`, `enrolenddate`, both
`optional`); the first mockup drew them as plain text, which was wrong. They move to section 2 and
keep their help.

### Moderators who apply through the same method

The owner's case, round 2 of this brainstorm (2026-11-19). In a separate-groups course, moderators
apply too. Candidates apply to a "Moderadores" group (today with that group's key, `'review'` mode);
the coordinator approves, and the new moderator joins the destination group **and** Moderadores. From
then on the new moderator is a member of Moderadores, so `groupscope` hands them every later
application whose group is Moderadores: they decide on the next moderators. Without
`enrol/apply:choosedecision` their approval grants the method's role, Student, and joins the
application's group, Moderadores. Today the coordinator has to remember to remove each new moderator
from Moderadores.

The cause is one membership meaning two things: "I belong to the moderation team" (its forum, its
messages) and "I decide the applications routed to this group" (`groupscope::narrowed_to()` reads
the decider's memberships). Four ways out, cheapest first:

| | Way | Code | What it fixes | Cost or limit |
|---|---|---|---|---|
| M1 | **A second method, "Moderator recruitment"**: role Non-editing teacher, no key, no choice, Moderadores among the groups joined on approval. Remove the key from the Moderadores group. | none | A keyless application reaches only those who see every group (S3), so membership in Moderadores routes nothing: no application has it as its group. The role comes from the right method. | Coordinators need `moodle/site:accessallgroups` (editing teachers have it; a coordinator who is a non-editing teacher needs a role that grants it). The candidate cannot say which group they want to moderate. |
| M2 | **A per-instance "Who decides" setting**: "The deciders of the application's group" (today) or "Only those who see every group". | small | M1, plus the candidate may *choose* a destination group (this design) without that group's moderators deciding. Routing and membership are decoupled per method. | One column (`customdec1`, `1` on, read strictly), one clause in `groupscope`'s scope, the notice recipients, tests. |
| M3 | **Groups that never route**: a group flagged (a core group custom field, the fleet's rule for per-group metadata) so membership in it gives no decision scope anywhere. | medium | The Moderadores case course-wide, whatever method. | A course-wide rule hidden on a group page; easy to set and forget. |
| M4 | **A role guard**: a decider may approve only when they could assign the role the approval grants (`get_assignable_roles()`), opt-in per instance. | small | A peer can never grant a role above their own, whatever the routing. | Off by default: a non-editing teacher cannot assign Student by default, so a global guard would stop every moderator approving students. |

**C-D19. The case, now: M1 as the way of working.** It needs no code, and the README gets a "Recruiting
moderators" recipe.

**C-D20. The case, in this design: M2.** Group choice makes routing by group common, so the method
that recruits deciders must be able to route nothing. The Groups section gets "Who decides
applications to this method?" beside the group question, and the summary strip says "decided only by
those who see every group". Recommended over M3, which hides a course-wide effect on one group's page.

**C-D21. M4, the role guard: offered, off by default.** A second line of defence for a recruitment
method, recorded on the decision (the role granted is already in `decidedrole`). The owner decides
whether it is worth a setting.

**What M2 is not: a key.** The owner read M2 as a key in the modal that marks a moderator. It is a
second enrolment method in the same course, a second card on the enrolment page ("Apply as a
moderator"): the method chosen decides the role, not anything typed. Moderadores is never offered in
any list; the recruitment method offers the destination groups and joins Moderadores on approval. The
key in the mockup's modal is the student method's own C-D5, unrelated.

**C-D25. Who sees the recruitment card.** Measured in `lib.php`: `enrol_page_hook()` returns a card
for every logged-in user, and a cohort restriction (`customint5`) only changes its body to
`cohortnonmemberinfo`; the card is never hidden. The gates, from what exists:

| | Gate | Code | Notes |
|---|---|---|---|
| G-a | The method restricted to a cohort, "Moderator candidates" | none | Non-members see the card, with "restricted". Filling the cohort needs `moodle/cohort:assign` (usually an administrator), or a rule-based cohort (`local_dynamic_cohorts`, on a profile field). |
| G-b | A per-instance "Hide this card from people who may not apply" | small | `enrol_page_hook()` returns null when `allow_apply()` refuses for the cohort; a window closed or a full method still shows its card, so a candidate learns why. |
| G-c | The method in key-only review mode, with Moderadores' key handed out in the invitation | none | Works only with M2 on: the key makes Moderadores the application's group, which M2 stops routing. The candidate then cannot choose a destination (C-D5); the coordinator picks it. The card shows to everybody, saying a key is needed. |

**Recommended: G-a with G-b** where an administrator or a rule fills the cohort; **G-c** where the
coordinator must invite without anybody's help. Either way an applicant who passes the gate creates
only a pending application that the coordinators alone decide (M2), so a leaked key or a wrong cohort
member costs a refusal, not a moderator.

**C-D26. Group seats, per method.** `groupseats` counts members the way `local_groupdist` does,
every member of any role, so a moderator joining Evening A takes one of its student seats, and a
full group would refuse a moderator candidate. **A per-instance "Group seats apply to this method"
(on by default, off for recruitment)**: off, the method neither shows nor checks seats; the
approval still adds a member, which the seats warning after an approval already reports.

**C-D27. The form warns about the configurations that recreate the problem**, as it already warns
about key-only mode (`key_only_warnings()`), computed from the values being edited:

- a method granting a role other than the site's student archetype, while "Who decides" is the
  group's deciders: "a moderator of a group could approve another moderator";
- a group offered here that another method of the course joins on approval with a non-student
  role (Moderadores offered on the student method);
- groups joined on approval whose members decide applications routed to them, while "Who
  decides" is the group's deciders.

The mockup's screen 2 shows the whole page for both methods, with a purple bar on every field
"Apply as a moderator" sets differently from "Apply as a student": that list is the recipe the README
gets (C-D19).

### Decisions taken (2026-11-19)

The owner accepted every recommendation of this document, with two refinements, in their words
(kept verbatim):

> C-D5 – O recomendado só precisaria que na página de análise mostrasse essa informação, a chave
> já inscreveu em um grupo e ele aguarda para entrar no próximo. Quem aprova precisa ter essa visão
> clara.
> C-D25 – A recomendação é boa, mas não conflita com o que o plugin Dimensions já faz em sua página
> de inscrição (landing page)? Penso que o plugin pode implementar, mas no tema precisar checar se o
> comportamento atual dele requer ajustes também par anão haver quebra.
>
> Demais decisões, seguir as recomendações

Asked which case C-D5 means, the owner chose the one across methods: somebody already enrolled in
the course by a group key through the student method applies through the recruitment method. Asked
how the theme should treat a hidden card for a visitor, the owner chose not to advertise it.

**C-D5, refined: the review page shows the applicant's other enrolments in the course.** Beside the
application, the decider reads a panel "Already in this course": each other enrolment of the
applicant there, with its method's name, its status, the role it gave, the groups it joined
through that method and how it was admitted (by a group key, or by whom and when), from the
durable record (`keyoutcome`, `joinedgroups`, `decidedby`) rather than recomputed. So "enrolled by
key in Evening A through Apply as a student; asks to moderate Morning C" is on screen before the
decision. Group names follow `groupvisibility::visible_to()` for the decider, like every other name
on the page. Stage G5.

A consequence measured in core, which the README recipe (C-D19) has to say:
`enrol/index.php` redirects anybody actively enrolled in the course to the course (5.3,
`enrol/index.php:87-94`), and theme_dimensions' landing page resolves an enrolled viewer to
`STATE_ENROLLED`, so **a student already in the course never sees the recruitment card**. Their way in
is the method's own form, `enrol/apply/apply.php?instance=<id>`, a link the coordination sends in the
invitation; G4 checks that the form admits an applicant enrolled through another method of the
same course and refuses nothing for that reason.

**C-D25, refined: G-a with G-b, and theme_dimensions stops advertising a hidden card to visitors.**
Read in theme_dimensions (main):

- A logged-in viewer is already consistent. `landing\resolver::resolve_apply()` keeps an apply
  method only when `allow_apply()` is true, and a cohort outsider fails it, so the theme already
  hides from them what G-b hides on core's enrolment page. Nothing changes there.
- The category classifier (`category\classifier`, case `'apply'`) judges the cohort per viewer from
  `customint5` and its `-1` sentinel; it never offers a cohort outsider anything. Nothing changes.
- **A visitor who is not logged in is the conflict.** `resolver::visitor_offers()` advertises every
  method `open_to_anybody()` passes, and that predicate deliberately ignores the cohort ("without a
  login the cohort cannot be known"). A recruitment method with its card hidden would be advertised
  to anybody who opens the course. The theme leaves such a method out of the visitor's offers (and
  so out of `login_way_in()`, which shares the predicate).
- The fact crosses through a public method of `enrol_apply_plugin`, `hides_card_from($instance,
  ?int $userid)`: true when the method's option is on, it restricts to a cohort and the user (null:
  a visitor) is not a member. The theme calls it behind `is_callable()`, as it already reaches
  `allow_apply()` and `is_full()`, so an older enrol_apply build keeps today's behaviour. It reads
  `instanceoptions`, which answers the defaults before the G2 upgrade has run.
- A separate theme_dimensions pull request after the enrol_apply one that adds the method, with a
  test of each side of the visitor predicate. The theme supports 5.3 only.

**C-D28. A decider who is also a learner in the course (raised and decided by the owner 2026-11-19: (b), with the lock of C-D29).**
The owner asked whether somebody who is a student in one group and a moderator in another of the
same course approves the applications of both, and hoped not. Today they do.
`groupscope::member_groups()` narrows a decider to every group they belong to in the course, read
from `{groups_members}` alone, whatever role or route put them there; and a role is held in the
course, not in a group. So the C-D5 case (enrolled by key in Evening A through the student method,
then a moderator of Morning C through the recruitment method) decides Evening A's applications too.
Core behaves the same way in separate-groups activities (that moderator sees Evening A's forum
and submissions); the question is whether approvals should be stricter than core.

| | Rule | Effect |
|---|---|---|
| (a) | Policy only: a moderator holds no learner enrolment in the course; the README recipe says so and the recruitment approval warns when the applicant already has one | Nothing enforced; one forgotten warning reopens it |
| (b) | **A membership written by an enrol_apply method narrows a decider only when the role that same method gave them can decide** (holds `enrol/apply:manageapplications` in the course). The membership and the role assignment are both stamped `enrol_apply` + the instance id, so the rule reads recorded facts; memberships of other origins (manual, other plugins) keep today's rule | The student-in-Evening-A membership, written by the student method with the Student role, gives no scope; the Morning C one, written by the recruitment method with Non-editing teacher, does |
| (c) | Scope only from memberships written by a method whose "Who decides" is "only those who see every group" (the recruitment method) | Precise for this fleet's set-up, but moderators placed in groups by hand would lose their scope |

**Decided: (b)**, with (a)'s warning on the review page (the C-D5 panel already shows the other
enrolment). It belongs to `groupscope`, so it reaches every reader through one helper (queue,
review, walk, choosers, notices, report), and needs gates on both halves: a learner membership
gives no scope, a deciding one still does. Stage G3.

**C-D29. The lock: who applies as a moderator cannot apply as a student (owner, 2026-11-19).**
The owner's words, kept verbatim:

> Então, uma trava de segurança. Quem se candidata como moderador não pode se candidatar como
> estudante.

- **Which method is which is read from the role, not from a new setting.** A method is a *deciding*
  method when the role it grants on approval holds `enrol/apply:manageapplications` in the course,
  and a *learner* method otherwise. A setting would be one more thing a teacher must remember;
  the role is what makes somebody a decider in the first place.
- **The lock lives in `allow_apply($instance, $userid)`**, the eligibility predicate the card, the
  form and the write door (`submit_application()`, inside its lock) already share, and which
  theme_dimensions' resolver asks too. A learner method refuses a user who holds, in the same
  course, an application or an enrolment through a deciding method (pending, deferred or
  active, as the queue's predicate and `is_enrolled()` read them). The refusal is a lang string
  that names nothing: "You have applied to take part in this course in another role, so you
  cannot apply here."
- **Two applications at once.** The submission lock is per method and user, so a moderator
  application and a student one sent together would both pass. The check takes a second lock on
  the course and user, around the read and the write, as the key admission does for places.
- **The other order stays open on purpose** (C-D5): a student who got in by key may apply as a
  moderator, and the decider sees that enrolment in the "Already in this course" panel. What that
  approval leaves (a person holding both roles in one course) is C-D28.
- Gates on both directions: a moderator applicant refused by the student method; a student
  admitted to the moderator method; the race held by the course lock. Stage G3, beside C-D28.

### The applicant's list

**C-D23. A combobox of the plugin's own, enhancing a plain `select`.** Measured in core 5.3
(`lib/amd/src/form-autocomplete.js:715-749`): in AJAX mode `processResults` returns either an array
of options or a string, and a string *replaces* the suggestions (`data-notice`, line 554). Core's
autocomplete therefore cannot show "eight groups and a note that there are more"; its static mode
shows every option with no note. The widget follows the ARIA combobox pattern (an input with
`role="combobox"`, a `listbox` popup, `aria-activedescendant`, a polite live region for the counts),
is drawn per the fleet Design System (the menu's panel and items, the status label for places), and
is started from `amd/src/enrol_page.js` on `core/modal:bodyRendered`, which already reads the form.
The list opens inline, pushing the fields below it down, rather than as a floating popup: core's
modal body scrolls, and would clip a popup positioned over its edge. No
web service: the offer is bounded by the instance's list and rendered in the form; the server
re-checks the posted group (the applicant's side, above).

**C-D24. What the list shows, and in which order.**

- Opened with nothing typed: up to **8** groups with places, in natural order (`groupfacts`), the same
  threshold as `groupfilter::THRESHOLD`, then the note "Showing 8 of 15 groups with places. Type to
  search all 22." When every group fits, no note.
- Typing: every offered group whose name contains the text, case- and accent-insensitive, those with
  places first, then the full ones (disabled, "Full"); at most 50, then "Refine your search".
- "No preference" heads the list when the mode is optional (C-D9).
- Without `local_groupdist` no group is full and no label shows; the first eight are simply the first
  eight.
- Fewer places first, or more places first? **Natural order**: a list that reorders as places fill
  makes the applicant hunt for a name they saw yesterday.

## Cluster and lifecycle

Everything is in the database: the mode on `{enrol}`, the offer in `enrol_apply_groups`, the choice
on the record. No cache, no node-local file. The seat check at submission runs inside the existing
per-applicant submission lock and reads `local_groupdist` live; no admission lock is taken because
a pending application takes no seat. Backup carries the offer rows through the existing group
mapping and `chosengroupid` through the record's; a restore without groups drops both (the record
keeps the id, as it keeps `keygroupid`). Privacy: `chosengroupid` joins the record's metadata and
export, named as the group's name where the subject may see it.

## Stages

| Stage | Content | Tier |
|---|---|---|
| G0 | Spike: the combobox of C-D23 inside the modal on 5.1, 5.2 and 5.3 (render, value kept after a validation error, the footer submit), and the plain `select` on `apply.php` without JavaScript | none, a scratch branch |
| G1 | **Merged, #147.** The instance form alone: C-D15&ndash;C-D18, no new setting; Behat on the form | 2 |
| G2 | **Merged, #145 (2026111900).** Schema: `enrol_apply_options` (groupchoice, deciders, groupseats, hidecard), `enrol_apply_offer`, `chosengroupid`, the deploy-window gate (`options_schema_ready()`), upgrade step, backup and restore, privacy | 3 |
| G3 | **Written (2026112001).** The lock (C-D29) in `allow_apply()`; C-D28's scope rule (b) in `groupscope`; `hides_card_from()` and the hidden card on core's enrolment page (C-D25); the Groups section's new question, the offer list, "Who decides" (C-D20) with its clause in `groupscope`, seats per method (C-D26), the hidden card (C-D25 G-b), the warnings (C-D27), validation (choice with a key-only mode, an empty offer in required mode) | 3 |
| G4 | **Merged, #148 (2026112000).** The applicant's form and its combobox (C-D23, C-D24: AMD module, template, `mdl grunt`), the submission's re-resolution, `applicantstate` wording | 3 |
| G5 | The decision side: the "Already in this course" panel (C-D5), the helper and its six SQL readers, review chip, pre-ticked chooser, bulk form, report column and filter | 3 |
| G6 | Behat for the modal, README (the table, the setting, the "Recruiting moderators" recipe with the invitation link), screenshots | 1 |
| T1 | theme_dimensions: leave an apply method whose card is hidden out of the visitor's offers, through `hides_card_from()` (C-D25); after G3 | 2 |

G1 can go first and on its own; G3&ndash;G6 follow in order, and T1 after G3. Every decision they need is
taken ("Decisions taken" above).

### What G2 leaves to the later stages

From the security read of G2 (2026-11-19), each a rule for the stage that adds the writer or reader:

- **`chosengroupid` is validated by its writer.** G4 writes it from a post: the group must be in the
  method's offer (`groupoffer::groups()`, which already joins the method's course), visible to the
  applicant, and with a seat where seats apply. The privacy export names it through `group_names()`,
  which reads `{groups}` by id with no course filter, so an unchecked id would export another
  course's group name.
- **`groupoffer::groups()` does not judge visibility.** Every caller showing a name runs it through
  `groupvisibility::visible_to()` for its reader (C-D7).
- **`groupoffer::set()` takes no lock.** Two concurrent saves of one method's offer meet the unique
  index and one of them fails whole; nothing duplicates. The instance form (G3) is the only writer.
- **A node still on the old code after the upgrade** (a rolling deploy) deletes a method without its
  options and offer rows; nothing reads them again, instance ids are never reused, and the
  `course_deleted` sweep removes them with the next course deletion.
