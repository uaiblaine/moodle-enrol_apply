# SC46: the enrolment methods page withholds the queue icon from a viewer.
s#if \(has_any_capability\(\['enrol/apply:manageapplications', 'enrol/apply:viewapplications'\], \$context\)\) \{#if (has_capability('enrol/apply:manageapplications', \$context)) {#;
