# SC47: the course report ignores separate groups.
s#if \(\$groups !== null\) \{\n(\s*)\[\$groupwhere, \$groupparams\] = groupscope::record_where#if (false) {\n${1}[\$groupwhere, \$groupparams] = groupscope::record_where#;
