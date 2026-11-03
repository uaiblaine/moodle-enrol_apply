# KO13: every unnamed instance is called after the key-only mode.
s#if \(empty\(\$instance->name\) && \\enrol_apply\\local\\groupkey::required\(\$instance\)\) \{#if (empty(\$instance->name)) \{#;
