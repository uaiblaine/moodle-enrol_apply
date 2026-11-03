# KQ6: a wrong key on Continue costs no try.
s#if \(\$this->is_wrong_key\(\$this->posted_key\(\)\)\) \{\n\s*keyattempts::record_failure\([^\n]*\n\s*\}\n##;
