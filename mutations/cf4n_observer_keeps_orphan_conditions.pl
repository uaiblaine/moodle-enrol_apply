# CF4N: the course_deleted sweep leaves the orphaned conditions of a disabled plugin behind.
s{\$DB->delete_records_select\(\n\s*conditions::TABLE,\n[^;]*\);}{}s;
