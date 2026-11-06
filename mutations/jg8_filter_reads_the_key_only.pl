# JG8: the group filter matches the key group alone.
s#\[\$where, \$params\] = groupscope::record_where\(\$alias, \[\(int\) \$value\], database::generate_param_name\(\)\);#[\$where, \$params] = ["{\$alias}.keygroupid = " . (int) \$value, []];#;
