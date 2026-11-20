# GD26: the application's group in SQL reads chosengroupid before the upgrade that adds it.
s#\$otherwise = instanceoptions::schema_ready\(\) \?#\$otherwise = true ?#;
