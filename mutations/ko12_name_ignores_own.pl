# KO12: a key-only instance is called after its mode even when the teacher named it.
s#if \(empty\(\$instance->name\) && \\enrol_apply\\local\\groupkey::required\(\$instance\)\) \{#if (\\enrol_apply\\local\\groupkey::required(\$instance)) \{#;
