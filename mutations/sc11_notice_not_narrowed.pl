# SC11: every course decider is told of every application, whatever their groups.
s#!\\enrol_apply\\local\\groupscope::allows_group\(\n(\s+)\(int\) \$instance->courseid,\n(\s+)\$keygroupid,#false && !\\enrol_apply\\local\\groupscope::allows_group(\n$1(int) \$instance->courseid,\n$2\$keygroupid,#;
