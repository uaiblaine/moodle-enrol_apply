# FC8: the group filter offers every group of the course, hidden ones included.
s#^\s*\$groups = array_intersect_key\(\$groups, array_flip\(groupvisibility::visible_ids\(\$groups, \(int\) \$USER->id\)\)\);\n##m;
