# JG6: the narrowing reads the joined groups alone, so a record approved before the column is lost.
s#"COALESCE\(\{\$alias\}\.joinedgroups, \{\$alias\}\.decidedgroups, ''\)"#"COALESCE({\$alias}.joinedgroups, '')"#;
