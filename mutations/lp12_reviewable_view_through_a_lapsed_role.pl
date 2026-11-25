# LP12: the course report's reviewable set reads the viewing capability with has_capability() alone.
s#groupscope::holds_in_course\(groupscope::VIEW, \$context\)#has_capability(groupscope::VIEW, \$context)#;
