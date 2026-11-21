# XA50: a new field's type taken from the form data is not checked.
s#if \(!\$field->get\('id'\) && isset\(\$data->type\)\) \{#if (false) {#;
