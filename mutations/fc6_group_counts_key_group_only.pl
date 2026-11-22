# FC6: the group facet counts a record in each group it joined, as the filter selects it.
s#" = \{\$column\} OR " \. \$DB->sql_position\(\$needle, \$list\) \. ' > 0\)'#" = {\$column})"#;
