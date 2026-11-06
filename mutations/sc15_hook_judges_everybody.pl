# SC15: the hook judges every user, not only holders of enrol/apply:manage, so a key admission (run as the applicant) is refused.
s#if \(!\$context \|\| !has_capability\('enrol/apply:manage', \$context\)\) \{#if (!\$context) {#;
