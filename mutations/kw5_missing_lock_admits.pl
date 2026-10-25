# KW5: a group lock not obtained is treated as obtained.
s#if \(\$locks === null\) \{#if (false) \{\n            \$locks = [];#;
