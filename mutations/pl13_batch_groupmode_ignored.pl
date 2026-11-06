# PL13: the batch treats visible groups as separate groups.
s#\$groupmode == NOGROUPS \|\| \$groupmode == VISIBLEGROUPS#false#;
