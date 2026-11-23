# FC11: a facet whose own filter is applied is tallied from the shared read, its own value in force.
s#if \(\$item\['applied'\]\) \{#if (false) {#;
