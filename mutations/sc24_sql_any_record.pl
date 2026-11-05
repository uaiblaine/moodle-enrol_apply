# SC24: the SQL form matches any record of the user enrolment, not only the newest.
s#AND scopesub\.keygroupid \{\$insql\}\n\s*AND NOT EXISTS \(\n.*?\n\s*\)\n(\s*\)",)#AND scopesub.keygroupid {\$insql}\n${1}#s;
