# GA4: The helper answers through has_capability() alone, so a lapsed reader holding the role is admitted.
s#if \(!self::holds_in_course\(\$capability, \$context\)\) \{#if (!has_capability(\$capability, \$context)) {#;
