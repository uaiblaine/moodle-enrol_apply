# GD27: the per-row application group reads chosengroupid before the upgrade that adds it.
s#\$choice = instanceoptions::schema_ready\(\);#\$choice = true;#;
