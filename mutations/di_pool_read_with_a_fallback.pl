# DI: read the setting with a fallback instead of one rule. Absent, empty and "the site had no
# choices to offer" are three spellings of the same state - nothing configured - and a fallback
# turns all three into filters the administrator declined to enable.
s{        \$stored = get_config\('enrol_apply', 'queuefilterfields'\);\n        if \(\$stored === false \|\| \$stored === null \|\| \$stored === ''\) \{\n            return \[\];\n        \}}{        \$stored = get_config('enrol_apply', 'queuefilterfields') ?: 'city,institution';}s;
