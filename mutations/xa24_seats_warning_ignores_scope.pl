# XA24: the seats warning without the reader's group scope.
s#\n                && groupscope::allows_group\(\$over->courseid, \$groupid, \$readerid\)##;
