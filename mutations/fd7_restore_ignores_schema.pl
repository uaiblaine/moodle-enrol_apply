# FD7: the restore writes the first decision before the upgrade.
s#if \(!enrol_get_plugin\(.apply.\)->first_response_schema_ready\(\)\) \{\s*return \[\];\s*\}##;
