# OB2: the sweep leaves orphaned options rows behind.
s{            \$DB->delete_records_select\(\n                'enrol_apply_options',\n                'NOT EXISTS \(SELECT 1 FROM \{enrol\} e WHERE e\.id = \{enrol_apply_options\}\.enrolid\)'\n            \);\n}{};
