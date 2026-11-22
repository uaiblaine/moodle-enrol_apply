# RT1: the retention notice links to the setting only for a site administrator.
s#if \(has_capability\('moodle/site:config', \\context_system::instance\(\)\)\) \{#if (true) {#;
