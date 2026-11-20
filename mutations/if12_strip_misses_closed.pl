# IF12: a window that has closed reads as open until its past date.
s#\} else if \(\$closes > 0 && \$closes < \$now\) \{#} else if (false) {#;
