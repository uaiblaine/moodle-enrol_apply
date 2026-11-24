# QW24: the capacity batch keeps only the last chunk's places taken, as a merge that overwrites.
s#\$taken \+= \$DB->get_records_sql_menu#\$taken = \$DB->get_records_sql_menu#;
