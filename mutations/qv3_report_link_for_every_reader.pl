# QV3: "Open the report" is offered to every reader of the queue, holder of enrol/apply:viewreports or not.
s#if \(groupscope::holds_in_course\(groupscope::REPORT, \$context\)\) \{#if (true) {#;
