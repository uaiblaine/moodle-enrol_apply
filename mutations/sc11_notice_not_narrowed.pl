# SC11: every course decider is told of every application, whatever their groups.
s#if \(!\\enrol_apply\\local\\groupscope::allows_group\(\(int\) \$instance->courseid, \$keygroupid, \(int\) \$user->id\)\) \{#if (false) {#;
