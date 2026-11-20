# BK1: the backup reads the new schema whatever the stored version.
s{\$schemaready = enrol_get_plugin\('apply'\)->options_schema_ready\(\);}{\$schemaready = true;};
