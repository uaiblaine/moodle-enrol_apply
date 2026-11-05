# SC30: allow_unenrol_user() judges a user who does not hold enrol/apply:unenrol.
s#if \(!\$context \|\| !has_capability\('enrol/apply:unenrol', \$context\)\) \{#if (!\$context) {#;
