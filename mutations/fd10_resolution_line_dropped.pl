# FD10: a record resolved later than its first response no longer says when.
s#\} else if \(\$first > 0 && \$first !== \$decided\) \{#} else if (false) {#;
