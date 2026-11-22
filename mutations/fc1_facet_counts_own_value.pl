# FC1: a facet is counted without its own filter's value.
s#if \(\$identifier === \$exclude\) \{#if (false) {#;
