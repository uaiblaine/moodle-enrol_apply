# GD6: the report's narrowing and group filter read the key group alone.
s#return \['\(' \. self::application_group_sql\(\$alias\) \. " \{\$insql\} OR "#return ['(' . "{\$alias}.keygroupid" . " {\$insql} OR "#;
