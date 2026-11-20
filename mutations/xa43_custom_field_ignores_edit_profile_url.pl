# XA43: a custom field stays editable for a user whose profile is edited elsewhere.
s#if \(!empty\(\$field->locked\) \|\| \$elsewhere\) \{#if (!empty(\$field->locked)) {#;
