# FD14: the report reads the first decision before the upgrade.
s#return enrol_get_plugin\(.apply.\)->first_response_schema_ready\(\);#return true;#;
