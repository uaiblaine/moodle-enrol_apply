# EV15: the observer completes an expected approval without re-reading the row, so an entry whose write never happened approves.
s#if \(!\$userenrolment \|\| \(int\) \$userenrolment->status !== ENROL_USER_ACTIVE\) \{#if (!\$userenrolment) {#;
