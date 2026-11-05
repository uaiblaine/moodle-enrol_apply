# SC32: confirm_enrolment() honours a posted group and role from a decider without enrol/apply:choosedecision.
s#if \(!has_capability\('enrol/apply:choosedecision', context_course::instance\(\$instance->courseid\)\)\) \{#if (false) {#;
