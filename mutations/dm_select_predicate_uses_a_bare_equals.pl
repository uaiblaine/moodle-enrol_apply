# DM: compare a closed vocabulary with a bare `=`. moodle_database::sql_equal() emits exactly that
# for PostgreSQL when asked for a case-sensitive comparison, and a bare `=` is case sensitive there,
# while on MariaDB it follows the column's case-insensitive collation - so the same filter over the
# same data answers differently on the two database families. Both named tests go red on PostgreSQL;
# on MariaDB the case-drift test stays green and only the accent test goes red.
s{\$wheres\[\] = \$DB->sql_equal\(\$offered->expression, ':' \. \$name, false, true\);}{\$wheres[] = \$offered->expression . ' = :' . \$name;}s;
