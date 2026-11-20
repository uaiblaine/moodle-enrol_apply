# GD25: the "Group chosen" column offered before the upgrade that adds its column.
s#if \(\\enrol_apply\\local\\instanceoptions::schema_ready\(\)\) \{\n(\s*\$calias = )#if (true) {\n$1#;
