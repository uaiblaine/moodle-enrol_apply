# GV6: a record that does not carry its visibility reads as visible to all.
s#if \(!isset\(\$group->id, \$group->courseid, \$group->visibility\)\) \{\n            return false;\n        \}\n        \$visibility = \(int\) \$group->visibility;#\$visibility = (int) (\$group->visibility ?? GROUPS_VISIBILITY_ALL);#;
