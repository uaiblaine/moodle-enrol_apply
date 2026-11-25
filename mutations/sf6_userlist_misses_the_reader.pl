# SF6: the user list of a user context adds nobody.
s/(if \(\$context->contextlevel == CONTEXT_USER\) \{\n\s*)\[\$isplugins, \$params\] = reportpersistents::is_plugins\(.r.\);\n.*?\n\s*\);\n/$1/s;
