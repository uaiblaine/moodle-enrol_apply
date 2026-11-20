# IF11: a window that has not opened yet reads as open.
s#\} else if \(\$opens > 0 && \$opens > \$now\) \{#} else if (false) {#;
