# KF4: a wrong key costs no try on a submission.
s#if \(\$this->is_wrong_key\(\$posted\)\) \{\n\s*keyattempts::record_failure\([^\n]*\n\s*\}\n##;
