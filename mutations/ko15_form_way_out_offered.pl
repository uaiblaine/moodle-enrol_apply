# KO15: the form offers "without the key" on a key-only instance after a wrong key.
s#if \(!\$mform->elementExists\('groupkey'\) \|\| groupkey::required\(\$this->get_instance\(\)\)\) \{#if (!\$mform->elementExists('groupkey')) \{#;
