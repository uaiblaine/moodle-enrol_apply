# SC4: scope() takes the instance queue for an application outside the operator's groups, so the anchor is outside the walk.
s#\n            && groupscope::allows\(\(int\) \$instance->courseid, \(int\) \$application->id, \(int\) \$USER->id\)\n#\n#;
