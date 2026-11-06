# SC28: allow_unenrol_user() no longer asks the group scope.
s#return \\enrol_apply\\local\\groupscope::allows\(\(int\) \$instance->courseid, \(int\) \$ue->id, \(int\) \$USER->id\);#return \$allowed;#;
