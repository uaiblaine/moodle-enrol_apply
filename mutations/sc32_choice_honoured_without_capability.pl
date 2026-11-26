# SC32: confirm_enrolment() honours a posted group and role from a decider without enrol/apply:choosedecision.
s#if \(\n\s*!\\enrol_apply\\local\\groupscope::holds_in_course\(\n\s*'enrol/apply:choosedecision',\n\s*context_course::instance\(\$instance->courseid\)\n\s*\)\n\s*\) \{#if (false) {#;
