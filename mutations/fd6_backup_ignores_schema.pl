# FD6: the backup reads the first decision before the upgrade.
s#\$firstready = enrol_get_plugin\(.apply.\)->first_response_schema_ready\(\);#\$firstready = true;#;
