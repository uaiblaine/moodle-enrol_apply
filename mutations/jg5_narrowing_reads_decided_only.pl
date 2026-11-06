# JG5: the report's narrowing reads the decider's choice alone, as before the column.
s#"COALESCE\(\{\$alias\}\.joinedgroups, \{\$alias\}\.decidedgroups, ''\)"#"COALESCE({\$alias}.decidedgroups, '')"#;
