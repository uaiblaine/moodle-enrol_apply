# Group key only: enrolment by key, with no approval queue

Status: **decided 2026-11-03.** The owner accepted every recommendation (D1&ndash;D8) and chose
D5 explicitly: the managers' notice is off by default in this mode, with a switch on the
instance. **Q0 (spikes) done 2026-11-03**: the two-step form holds on 5.1&ndash;5.3 with one
change, an in-form "Continue" no-submit button instead of the footer (see Q0). **Q1&ndash;Q4
written 2026-11-03** as four stacked pull requests, #114 (Q1), #115 (Q2), #116 (Q3) and #117 (Q4),
each with its gate in its description; not merged (the owner's call). Two departures from this
plan, both found by the mutation sweeps: no KO18 (a key-only form has no "without the key"
element, so validation needed no clause for it), and KO16 holds the key field's required mark
rather than the server's refusal, which `key_error()` makes on its own. The README screenshots
still show the form before Q2 and need recapturing. Mockups:
[`group-key-only-mockup.html`](group-key-only-mockup.html). Builds on
[`group-enrolment-key.md`](group-enrolment-key.md) (G1&ndash;G15, stages K0&ndash;K8, all
merged), whose decisions stand unless this file says otherwise.

## The request

The owner's words, kept verbatim:

> Para o plugin moodle-enrol_apply, adicionar a funcionalidade de inscrição só com chave de
> grupo. Hoje o usuário pode se inscrever direto quando tem a chave e ir para a fila de
> aprovação, quero manter o comportamento e adicionar o novo só com "chave de inscrição", sem
> fila de aprovação. [...] pode haver confusão na experiência do usuário professor que configura
> e aluno que solicita a inscrição, por exemplo, já vejo a necessidade de ajustar o modal com
> mais destaque no campo para informar a chave de acesso.

So: a third mode for an instance. Today's two modes stay exactly as they are; in the new one
an applicant **must** hold a valid group key, is enrolled at once in that group after the
required fields and questions, and nothing ever waits for a decision. Without a valid key there
is no enrolment through this method at all.

What this is not: `enrol_self` with group keys. That plugin also needs a course key, asks no
questions and keeps no record. Here the required profile fields, the questions, the enrolment
window, the cohort restriction, both capacities, the durable record, the report and
`local_groupdist`'s seats all still apply. Only the queue goes.

## What changes against today

Today (`groupkey::MODE_ANY`), every route that a valid key cannot finish falls back to an
ordinary application waiting for a decision. In the new mode there is nobody to decide, so each
fallback becomes a refusal or disappears:

| Situation | Today, `'any'` | Key only, `'required'` |
|---|---|---|
| Valid key, a place and every seat free | Admitted (`KEY_ADMITTED`) | Same |
| No key | Ordinary application | **Refused**: the form requires the key |
| Wrong key | Form error, then the `withoutkey` box | Form error only; the box never appears |
| Five wrong keys in 15 minutes (`keyattempts`) | Field locks; the application stays possible | Field locks; **nothing can be submitted** until the window passes |
| Valid key, `customint4` places full | Application, `KEY_NO_PLACE` | **Refused**, nothing written (D1) |
| Valid key, a target group out of seats | Application, `KEY_GROUP_FULL` | **Refused**, nothing written (D1) |
| Lock timed out (`KEY_SEAT_UNCONFIRMED`) | Application, flagged | **Refused**, "try again", nothing written |
| Window, cohort, `customint6`, applicant limit, guest, log-in-as | Refused | Same |

`KEY_NO_PLACE`, `KEY_GROUP_FULL` and `KEY_SEAT_UNCONFIRMED` are therefore never written by a
key-only instance. They stay in `submission::KEY_OUTCOMES` for the other mode and for rows
recorded before a teacher switched modes.

## Decisions

All accepted 2026-11-03.

| # | Question | Decision |
|---|---|---|
| **D1** | Valid key, but no place or no seat: refuse, or queue as overflow? | **Refuse, writing nothing.** "No queue" is the promise of the mode. An overflow queue would be a fourth mode, added only if a course needs it. |
| **D2** | A third value of the existing setting, or a separate switch? | **A third value of `customchar1`**: `''` off, `'any'` key or review, `'required'` key only. One question ("what does a key do here?"), and no "key only, keys off" combination to explain. |
| **D3** | Trim the typed key? | **Yes, together with replacing `passwordunmask` with a plain `text` element.** A group key with wrapping whitespace cannot be typed today (the summary already flags it as `untypable`), so trimming cannot make two keys collide; it fixes the commonest paste. `groupkey::resolve()` keeps its exact compare; the form trims before it. Both modes. |
| **D4** | Two-step modal, or one form with an emphasised key? | **Two steps in key-only mode** (key first, then "you will join group X" and the rest); **an emphasised key block in key-or-review mode**. |
| **D5** | Tell managers about every admission? | **Off by default in key-only mode, with a switch on the instance.** In key-or-review mode the notice stays as G8 decided (always sent). |
| **D6** | Default instance name by mode? | **Yes.** An unnamed key-only instance is called "Enrolment with a group key", not "Course enrol confirmation". |
| **D7** | The comment box (`customint7`) in key-only mode? | **Kept**, labelled as a comment for the teacher: it lands in the record and the report, and nobody reads it at the moment of enrolment. |
| **D8** | Per-instance group lists (G3b) now? | **No.** Recorded as the natural next step: two apply instances in one course, one per mode, accept the same keys until it exists. |

### What D5 is, concretely

`customchar2` (free on this plugin: `customint1`/`customint2` carry legacy meanings
`db/upgradelib.php` still reads). Strict reading, like `customchar1`: only `'1'` means "notify",
anything else, the empty default included, means "do not". It is **read only in key-only
mode**: `'any'` keeps sending the notice whatever the field holds, so no upgrade step and no
existing instance changes. The edit form shows the select only when `customchar1` is
`'required'` (`hideIf`); on 5.3 a hidden element is disabled and not posted, which is harmless
here because core's `update_instance()` writes only the fields it was given, so a hidden switch
keeps its stored value. Backup carries it verbatim, which is right: it is a preference, not a
site-local id.

## Where the confusion is, and what answers it

### The teacher configuring the instance

| # | Scenario | Answer | Stage |
|---|---|---|---|
| P1 | Key-only mode, no group of the course has a key | Nobody can enrol, silently. The summary on the edit form becomes a **warning** ("nobody can enrol through this method"), with the "Manage groups and their keys" link. | Q3 |
| P1b | Key-only mode, every keyed group is full (groupdist) | Same warning, worded for seats. The per-group "Full: its key admits nobody now" line already exists. | Q3 |
| P2 | Switching from key-or-review to key-only with applications waiting | They look abandoned. The edit form warns "N applications are still waiting for a decision; switching does not decide them". The queue keeps working for them. | Q3 |
| P3 | The two capacities in key-only mode | Admissions are active rows, so `customint3` and `customint4` count nearly the same people (old pending rows aside). Help text says so: the lower one wins. No `hideIf`. | Q3 |
| P4 | The default name on the card | D6: `get_instance_name()` override, used only when the instance has no name of its own. | Q1 |
| P5 | One managers' notice per admission | D5. | Q1 |
| P6 | Two apply instances, one per mode | Both accept the same keys (G3a). Documented in the help and the README; D8 is the fix. | Q4 |
| P7 | `enrol_self` with group keys enabled in the same course | The same key enrols there without the questions. The summary warns when the course has an enabled `enrol_self` instance with `customint1` (group keys) on. | Q3 |
| P8 | Short or guessable keys | Out of scope: the key is now the only gate, and the throttle (five per user per 15 minutes) is the defence. It is per user, so a site with open self-registration can multiply it; the README says so. | Q4 |
| P9 | The queue and the report | The queue of a key-only instance says "this method enrols by group key only; nothing waits for a decision" when it is empty. The report already shows the admission route. | Q3 |

### The applicant

| # | Scenario | Answer | Stage |
|---|---|---|---|
| A1 | Valid key | Enrolled, sent to the course, told which group (as today). | Q1 |
| A2 | No key | The **card** says so before any form ("this course needs the group key your teacher gave you"); the button reads "Enrol with a key". | Q2 |
| A3 | A typo | Field error; in the two-step modal only the key has been typed, so nothing is lost. | Q2 |
| A4 | Throttled | The lock sentence loses its "send for review" half: "try again in a few minutes, or ask whoever gave you the key". No count of tries left (an oracle). Validation puts an error on the lock element, so the footer button cannot reach the write door; the door refuses a keyless post anyway. | Q1, Q2 |
| A5 | Pasted with a trailing space | D3. | Q2 |
| A6 | Valid key, no place or no seat | D1. The **form** catches the common case: `validation()` checks places and seats (advisory, unlocked) and errs on the key field, naming the group (G9: they hold its key), so the form stays open. The door's check under `lock_admission()` stays the guard; only a race reaches the refusal page. | Q1, Q2 |
| A7 | Lock timeout | Refused, "try again". | Q1 |
| A8 | Key rotated while the modal was open | Refused at the door, which resolves the key again; `validation()` resolves it too, so on the next submit the field says why. | Q1 |
| A9 | Wording that is false in this mode | `youwillchecknddetails` ("submitted for review"), `detailsthattravel_desc` ("sent to whoever reviews"), `groupkey_help` ("optional"), `startapplication`, `submitapplication`, the modal title. Each gets a key-only variant chosen by a literal `match`. | Q2 |
| A10 | The comment box | D7: `commentlabel` default changes in key-only mode, not a custom `customtext2`. | Q2 |

## The modal

Today the key is a small `passwordunmask` in its "Click to enter text" state, with no heading,
above "Details that travel with your application". It reads as an optional inline field, not as
the thing that decides what submitting does. Two shapes, one per mode (D4).

**Both modes: the key is a block of its own.** Still not under a `header` element (K5: a header
stays open over every element that follows, and an instance asking for no fields would render
its questions inside it). Instead a static intro element followed by the key element, both styled
by their `fitem` ids under a class the plugin puts on the form (`enrol-apply-application`): plugin
CSS loads before the theme, so only a selector qualified by a plugin-owned class wins. A tinted
panel, a heading-sized label, one sentence on what the key does in this mode, a full-width
`text` input (D3) with `autocomplete="off"`, `spellcheck="false"`, `autocapitalize="off"`.

**Key-only mode: two steps in one `dynamic_form`.**

1. Step 1 renders the teacher's introduction, the key block and a **"Continue" button inside the
   form**, registered with `registerNoSubmitButton()`. In the modal, `enrol_page.js` hides the
   footer's Save button on this step; on `apply.php` the page draws Continue and Cancel only.
2. Continue with a key that resolves makes `definition()` build the whole form: a confirmation
   line ("You will join **Turma B**"), the key carried as a hidden field, the details, the
   questions, the comment. Validation does not run on a no-submit post, so nothing arrives in
   error. The footer's Save comes back as "Enrol me".
3. Submitting step 2 runs today's validation and write door unchanged.

The invariant that keeps this safe, and that Q1 tests: **the step is derived from the posted key
on every request, never from a posted step marker**, and the full form is built whenever the key
resolves. So there is no request on which a resolving key is validated against a form missing
its required fields. An instance asking for nothing at all goes from step 1 straight to the
admission, which is right: there is nothing to ask.

Q0 settled both open mechanics; its section below has the measurements and what they require of
Q1 and Q2.

**Key-or-review mode** keeps one form: the key block with the "optional" label and the
`withoutkey` escape after a wrong key, exactly as K5 built it. An explicit "I have a key / I do
not" choice upfront is a possible later refinement, not part of this plan.

## Stages

Each stage leaves the plugin releasable. Version bump and `CHANGELOG.md` in each stage that
touches lang strings, settings or `amd/`. Supported range stays `[501, 503]`. Q1 is tier 3
(the state machine, a route past approval): `security-diff-read` over its diff,
`mdl ci moodle-enrol_apply --matrix --behat`, and an MDL Shield review if the month's quota
allows. Q2 and Q3 are tier 2 unless they touch the door.

### Q0. Spikes &mdash; DONE 2026-11-03, nothing committed

Throwaway scripts piped into `docker exec -i -u www-data <stack>-webserver-1 php`, read-only,
run on **m501 (5.1.8) and m503 (5.3+)**. m502 was down and was not started: `lib/formslib.php`,
`lib/form/classes/dynamic_form.php`, `lib/form/classes/external/dynamic_form.php`,
`lib/form/amd/src/modalform.js`, `lib/form/text.php`, `lib/form/passwordunmask.php` and
`lib/amd/src/modal.js` are byte-identical between the 5.2 and 5.3 checkouts (`cmp`), so 5.2 is
covered by 5.3. Two harness traps, neither a product fact: on m501 `arg_separator.output` is
`&amp;`, so `http_build_query()` needs `'&'` passed explicitly; and the web service path needs
`sesskey` in the request (`$_GET`), because `_process_submission()` calls `confirm_sesskey()`,
which reads the request and not the form data. One case per process: formslib caches
`no_submit_button_pressed()` in a function-level `static`.

1. **The two-step form works, through `\core_form\external\dynamic_form::execute()` and through
   the page transport alike, identically on 5.1 and 5.3**, once the Continue button is a
   no-submit button **registered on both steps**. Registered on step 1 only, the post that
   carries a resolving key builds step 2, which does not know the button, so the post reads as a
   real submit and step 2 arrives with every required field in error ("You must supply a value
   here."). That was the first run; registering it in both branches of `definition()` fixed it.
   A throwaway `dynamic_form` (key, then a required `city`), eight cases, the same rows on both
   transports and both stacks:

   | Posted | Submitted | Step rendered | Key error | City error |
   |---|---|---|---|---|
   | Continue, valid key with wrapping spaces | no | **2** | &mdash; | **none** |
   | Continue, wrong key | no | 1 | yes (from `definition_after_data()`) | &mdash; |
   | Continue, empty key | no | 1 | yes (from `definition_after_data()`) | &mdash; |
   | Footer Save on step 1, valid key | no | 2 | &mdash; | **yes: every required field** |
   | Footer Save on step 1, wrong key | no | 1 | yes (from `validation()`) | &mdash; |
   | Step 2, city filled | **yes** | &mdash; | &mdash; | &mdash; |
   | Step 2, city empty | no | 2 | &mdash; | yes |
   | Step 2, carried key no longer resolves | no | **1** | yes (from `validation()`) | &mdash; |

   What follows for Q1 and Q2:
   - **`validation()` does not run on a no-submit post** (`validate_defined_fields()` returns
     false first), so on Continue the wrong-key message, the throttle count and the capacity
     check (Q0.4) live in `definition_after_data()`, via `setElementError()`. On the final
     submit they stay in `validation()`, as today.
   - **On the web service path the form is built twice per Continue**: `execute()` builds a
     second instance to render after a no-submit (the log shows `definition`, `dad` twice), so a
     throttle count in `definition_after_data()` must happen once per request (a request-level
     guard), or one wrong key costs two tries. The page transport builds it once.
   - **The footer's Save must not be offered on step 1** (row 4). In the modal `enrol_page.js`
     hides it; on the page, step 1 draws only Continue and Cancel. Enter in the key field
     submits through the form's first submit button, which is Continue, and ModalForm catches a
     click on `input[type=submit][data-no-submit]` (the rendered button carries
     `data-no-submit="1"` and `data-skip-validation="1"` on both branches).
   - **The hidden field carries the key as posted, wrapping spaces included**, and the resolver
     trims nothing, so the trim (D3) is applied where the key leaves the form, in
     `process_dynamic_submission()` before `submit_application()`, and wherever the form itself
     resolves it.
2. **The footer can follow the step, by reading the code; Q2's `@javascript` Behat holds it.**
   Every body ModalForm shows (the first load, the re-render after a no-submit, the re-render
   after a server-side validation error) goes through `core/modal`'s `setBodyContent()`, which
   dispatches a bubbling `core/modal:bodyRendered` DOM event once the body is in place (5.1
   `modal.js:585-586`, 5.3 `:616-617`). So `enrol_page.js` listens for it, reads
   `form[data-enrol-apply-step]` (the form attribute the spike rendered), hides the footer's
   `[data-action='save']` on step 1, and on step 2 shows it and calls
   `modal.setButtonText('save', ...)`. Hide with the `hidden` attribute, not `disabled`:
   `enableButtons()` re-enables every `[data-action]` after each re-render.
   `ModalForm`'s `saveButtonText` is applied only once, when the modal is created, so it cannot
   follow the step on its own. Not driven in a logged-in browser here (UI checks do not type a
   password); the Q2 scenario is the proof.
3. **The `text` element** with `autocomplete="off"`, `spellcheck="false"` and
   `autocapitalize="off"` renders those attributes as given and hands back the posted value raw
   (`PARAM_RAW`), on 5.1 and 5.3: `"  turmaB-2027\t"` comes back with its whitespace, an inner
   space is kept, and nothing refuses a value as `passwordunmask` does. `passwordunmask`'s
   refusal is `$value !== trim($value)` on both branches, so **PHP's `trim()` is exactly the set
   the group page already keeps off the ends of every key it stores**: trimming with it cannot
   change a key typed there. A trailing non-breaking space (U+00A0), common in a key copied
   from a web page or an e-mail, is **not** in that set and survives `trim()`; it then reads as
   a wrong key. Recommendation for Q2: a value whose ends are Unicode spaces outside `trim()`'s
   set gets its own message ("your key starts or ends with an unusual space; type it again")
   and costs no try, the way K5 treats wrapping whitespace today. The browser's password
   manager was not measured (the pane holds no saved passwords); a `type="text"` field is not
   one password managers fill, where `passwordunmask` renders `type="password"`.
4. **The unlocked capacity pre-check is cheap.** `capacity::places_full()` +
   `key_route_groups()` + `groupseats::full()` with `local_groupdist` installed, measured with
   `perf_get_reads()` on a real instance: **4 statements, 0.4&ndash;0.8 ms** warm, and **10
   statements, 12&ndash;33 ms** the first time in a process (groupdist's custom field lookup
   warming up), on both stacks. A web request is that first time, so a submit pays about 10
   statements for the pre-check and the door's locked check adds about 4. The error lands on
   the key field in both transports by the same mechanism as the wrong key (row 2 on Continue,
   row 5 on a final submit).

### Q1. Server: the mode, the door, the notice, the name

Delivered in #114.

- `groupkey::MODE_REQUIRED = 'required'`, added to `MODES`; `enabled()` true for both key modes;
  a new `required()`. The site default `enrol_apply/groupkeys` gains the option through `MODES`.
- `edit_form.php`: the select gains the third option and its help; `customchar2` select (D5),
  shown only in key-only mode.
- `apply_or_admit()`: in key-only mode a key that resolves to nothing, or a ticked `withoutkey`,
  returns `application_result::refused()` with the mode's sentence; the three fallbacks return
  `refused()` too, and **write nothing**. `withoutkey` is ignored in key-only mode, not honoured.
- `send_application_notification()`: on an admission in key-only mode, sent only when
  `customchar2 === '1'`.
- `get_instance_name()`: D6, when `$instance->name` is empty.
- `application_form::validation()`: the throttled form errs on its lock element in key-only mode.
- Tests and `mutations/gates.conf` lines for every guard: keyless refused; wrong key refused;
  `withoutkey` ignored; each fallback refused with zero rows in `{user_enrolments}`,
  `enrol_apply_submission` and `enrol_apply_answer`; the notice off by default, on with `'1'`,
  unaffected in `'any'`; an unknown `customchar1` still reads as off; the name.

### Q2. The applicant's surfaces

Delivered in #115.

- Card (`enrol_page_hook()`): key-only body and button label.
- Form: the key block (both modes), `text` and trimming (D3, both modes), the two-step flow in
  key-only mode, the advisory capacity check in `validation()`, the key-only variants of every
  sentence in A9 and of the comment label (D7).
- `amd/src/enrol_page.js`: the footer label per step; `amd/build` rebuilt in the same commit.
- Behat: key-only happy path in two steps; no key; wrong key with no escape offered; group full
  (with groupdist where CI installs it); key-or-review mode unchanged (the existing feature).

### Q3. The teacher's surfaces

Delivered in #116.

- Edit form summary: the P1/P1b warning, the P2 pending-applications warning, the P7 `enrol_self`
  warning, the P3 help text.
- Queue: the P9 empty-state sentence for a key-only instance.

### Q4. Documentation

Delivered in #117, without the screenshots.

README (the three modes, who is told, P6, P8), CHANGELOG, the repo `CLAUDE.md` bullet on
`customchar1` (three values, `customchar2`, and its "compared as typed" sentence, which D3
qualifies: the form trims, the resolver still does not), screenshots for the card and both steps
of the modal.

## Strings

English; the pt_br column follows the plugin's rule ("inscrição", never "matrícula").

| Key | en | pt_br |
|---|---|---|
| `groupkeys` | What a group enrolment key does | O que a chave de inscrição do grupo faz |
| `groupkeys_off` | Nothing: every application is reviewed | Nada: toda inscrição passa por análise |
| `groupkeys_any` | A valid key enrols at once; without one, the application is reviewed | Uma chave válida inscreve na hora; sem ela, a inscrição passa por análise |
| `groupkeys_required` | Key only: a valid key enrols at once, and nobody can enrol without one | Só com chave: uma chave válida inscreve na hora, e ninguém se inscreve sem ela |
| `admissionnotice` | Tell managers about each enrolment with a key | Avisar os gestores a cada inscrição com chave |
| `pluginname_keyonly` | Enrolment with a group key | Inscrição com chave de grupo |
| `keyonlycard` | This course needs the group enrolment key your teacher gave you. | Este curso exige a chave de inscrição do grupo que seu professor forneceu. |
| `enrolwithkey` | Enrol with a key | Inscrever-se com chave |
| `groupkeyblock_any` | Have a key for your group? Enter it to be enrolled straight away. Without one, your application is reviewed. | Tem a chave do seu grupo? Digite-a para entrar na hora. Sem ela, sua inscrição passa por análise. |
| `groupkeyblock_required` | Enter the key your teacher gave you, exactly as you received it. It decides your group. | Digite a chave que seu professor forneceu, exatamente como recebeu. Ela define o seu grupo. |
| `groupkeyrequired` | Enter your group enrolment key to continue. | Digite a chave de inscrição do grupo para continuar. |
| `groupkeyinvalid_required` | This key is not valid for this course. Check it and try again. | Esta chave não é válida para este curso. Confira e tente de novo. |
| `groupkeylocked_required` | Too many incorrect keys. Try again in a few minutes, or ask whoever gave you the key. | Muitas chaves incorretas. Tente de novo em alguns minutos ou fale com quem lhe passou a chave. |
| `keyjoins` | You will join {$a}. | Você vai entrar no grupo {$a}. |
| `keygroupfull` | Your key for {$a} is valid, but that group has no seats left. | Sua chave do grupo {$a} é válida, mas o grupo não tem mais vagas. |
| `keynoplace` | Your key is valid, but this course has no places left. | Sua chave é válida, mas o curso não tem mais vagas. |
| `keyseatunconfirmed` | Your key is valid, but a place could not be confirmed just now. Try again. | Sua chave é válida, mas não foi possível confirmar a vaga agora. Tente de novo. |
| `detailsthattravel_desc_required` | These details are kept with your enrolment. Editing them here does not change your profile. | Estes dados ficam registrados com a sua inscrição. Alterá-los aqui não muda o seu perfil. |
| `comment_required` | A comment for your teacher | Um comentário para o professor |
| `keyonlynokeys` | No group in this course has an enrolment key, so nobody can enrol through this method. | Nenhum grupo deste curso tem chave de inscrição, então ninguém consegue se inscrever por este método. |
| `keyonlyallfull` | Every group with a key is full, so nobody can enrol through this method now. | Todos os grupos com chave estão lotados, então ninguém consegue se inscrever por este método agora. |
| `keyonlypending` | {$a} applications are still waiting for a decision. Switching to key only does not decide them; they stay in the queue. | {$a} inscrições ainda aguardam decisão. Mudar para só com chave não as decide; elas continuam na fila. |
| `keyonlyselfkeys` | This course also has self enrolment with group keys: the same keys enrol there without these questions. | Este curso também tem autoinscrição com chaves de grupo: as mesmas chaves inscrevem lá sem estas perguntas. |
| `keyonlyqueue` | This method enrols by group key only; nothing waits for a decision. | Este método inscreve só por chave de grupo; nada aguarda decisão. |

The two footer labels reuse core's strings, `moodle/continue` ("Continue") and `enrol/enrolme`
("Enrol me"). The existing `groupkeys` and `groupkeys_any` change meaning slightly (the setting is now a
question with three answers): a `lang/en/deprecated.txt` entry is not needed, because the key
names stay and only the wording sharpens.

Out of scope, recorded so nobody assumes otherwise: an overflow queue for full groups (D1), a
per-instance list of groups (D8), judging key strength (P8), an explicit "I have a key / I do
not" choice in key-or-review mode, and the Moodle App beyond the web view.
