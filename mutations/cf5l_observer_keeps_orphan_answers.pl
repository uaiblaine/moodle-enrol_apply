# CF5L: the course deletion sweep leaves answers whose record is gone.
s{\n\s*\$DB->delete_records_select\(\n\s*'enrol_apply_answer',\n\s*'NOT EXISTS \(SELECT 1 FROM \{enrol_apply_submission\} s WHERE s\.id = \{enrol_apply_answer\}\.submissionid\)'\n\s*\);\n}{\n}s;
