# SC50: the report reads the application's group alone, not the decided or joined groups.
s#return \['\(' \. self::application_group_sql\(\$alias\) \. " \{\$insql\} OR " \. implode\(' OR ', \$likes\) \. '\)', \$params\];#return ['(' . self::application_group_sql(\$alias) . " {\$insql})", \$params];#;
