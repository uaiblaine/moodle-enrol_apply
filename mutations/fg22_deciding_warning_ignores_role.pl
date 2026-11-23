# FG22: the warning does not follow "Role on approval": no hideIf on the offered roles that do not decide.
s#\n\s*if \(\$learner\) \{\n\s*\$mform->hideIf\('decidingbygroups', 'roleid', 'in', implode\('\|', \$learner\)\);\n\s*\}##;
