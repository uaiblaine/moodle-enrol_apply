# GD1: the application's group in SQL forgets the chosen group.
s#\$otherwise = instanceoptions::schema_ready\(\) \? "\{\$alias\}\.chosengroupid" : '0';#\$otherwise = '0';#;
