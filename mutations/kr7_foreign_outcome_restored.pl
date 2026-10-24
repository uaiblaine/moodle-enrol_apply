# KR7: the restore keeps an outcome outside the vocabulary.
s#if \(!in_array\(\$outcome, submission::KEY_OUTCOMES, true\) \|\| \$outcome === submission::KEY_NONE\) \{#if (\$outcome === submission::KEY_NONE) \{#;
