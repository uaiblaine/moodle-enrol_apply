# EV4: complete_approval() never logs, so core's "Edit enrolment" route, which runs only the hook
# pass, leaves no approval in the log.
s#if \(!\\enrol_apply\\local\\decisionlog::is_owed\(\(int\) \$userenrolmentid\)\) \{#if (false) {#;
