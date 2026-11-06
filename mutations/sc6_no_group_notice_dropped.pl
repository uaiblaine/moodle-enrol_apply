# SC6: a moderator in no group gets an empty queue instead of core's notice.
s#if \(\$table->not_in_any_group\(\)\) \{#if (false) {#;
