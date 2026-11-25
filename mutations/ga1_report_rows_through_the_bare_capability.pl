# GA1: The report rows service reads the capability bare behind validate_context(), so a lapsed reader admitted as a temporary guest passes.
s#groupscope::require_in_course\('enrol/apply:viewreports', (\$context)\);#require_capability('enrol/apply:viewreports', $1);#;
