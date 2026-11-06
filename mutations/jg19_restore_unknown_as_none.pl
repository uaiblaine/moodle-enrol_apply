# JG19: the restore turns groups joined that are not known into "joined none".
s#(\(\$data->joinedgroups \?\? ''\)\) === ''\n\s*)\? null#${1}? ''#;
