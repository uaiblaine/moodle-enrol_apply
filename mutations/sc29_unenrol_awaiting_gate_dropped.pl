# SC29: allow_unenrol_user() judges an active enrolment as it does an application.
s#if \(!\$allowed \|\| !\\enrol_apply\\local\\queue::is_awaiting_decision\(\$ue\)\) \{#if (!\$allowed) {#;
