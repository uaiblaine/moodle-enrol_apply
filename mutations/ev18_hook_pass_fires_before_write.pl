# EV18: the hook pass triggers the approval at once instead of parking it, before core writes the row.
s#if \(\$written\) \{#if (true) {#;
