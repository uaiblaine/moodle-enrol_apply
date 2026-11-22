# RX3: "Still open" links to the queue for every reader of the report.
s#if \(\$context instanceof context_course && queue::listing_scope\(\$enrolid\)->allowed\) \{#if (\$context instanceof context_course) {#;
