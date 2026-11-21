# XA30: the backup no longer reads the anonymize setting, so an anonymised backup carries the comment, the record and the answers.
s#if \(\$this->task->get_setting_value\('anonymize'\)\) \{#if (false) {#;
