# XA44: the form no longer refuses a standard value longer than its column.
s#if \(\$maxlength > 0 && \\core_text::strlen\(\$value\) > \$maxlength\) \{#if (false) {#;
