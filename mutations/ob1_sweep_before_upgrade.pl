# OB1: the course_deleted sweep reads the new tables whatever the stored version.
s{        if \(enrol_get_plugin\('apply'\)->options_schema_ready\(\)\) \{\n            \$DB->delete_records_select\(\n                'enrol_apply_offer'}{        if (true) \{\n            \$DB->delete_records_select(\n                'enrol_apply_offer'};
