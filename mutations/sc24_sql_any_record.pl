# SC24: the SQL form matches any record of the user enrolment, not only the newest.
s#AND \{\$group\} \{\$insql\}\n\s*AND NOT EXISTS \(\n.*?\n\s*\)\n(\s*\)",)#AND {\$group} {\$insql}\n${1}#s;
