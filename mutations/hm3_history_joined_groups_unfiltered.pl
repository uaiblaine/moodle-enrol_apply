# HM3: the history names the joined groups without the report formatter's visibility check.
s#\$joined = \$formatter::joined_groups\(\$prior->joinedgroups, \$prior\);#\$joined = s(implode(', ', array_column(\\enrol_apply\\local\\submission::parse_joined_groups(\$prior->joinedgroups), 'name')));#;
