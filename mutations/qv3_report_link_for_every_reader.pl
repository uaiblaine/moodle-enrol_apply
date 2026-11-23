# QV3: "Open the report" is offered to every reader of the queue, holder of enrol/apply:viewreports or not.
s#if \(has_capability\(groupscope::REPORT, \$context\)\) \{#if (true) {#;
