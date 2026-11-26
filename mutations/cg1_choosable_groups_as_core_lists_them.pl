# CG1: choosable_groups() takes core's groups_get_all_groups() whole, whose visibility reads a lapsed course role.
s#\n\s*\$visible = groupvisibility::visible_ids\(\$groups, \(int\) \$USER->id\);\n\s*\$groups = array_intersect_key\(\$groups, array_flip\(\$visible\)\);##;
