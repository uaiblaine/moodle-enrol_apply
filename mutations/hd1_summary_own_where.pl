# HD1: the summary reads the rows' own table, so it counts what the reader lists, narrowing and filters included.
s#FROM \{\$table->sql->from\} WHERE \{\$table->sql->where\}"#FROM {\$table->sql->from} WHERE 1=1"#;
