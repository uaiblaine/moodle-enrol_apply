# DM: compare a closed vocabulary with a bare `=`. moodle_database::sql_equal() emits exactly that
# for PostgreSQL when asked for a case-sensitive comparison, and a bare `=` is case sensitive there,
# while on MariaDB it follows the column's case-insensitive collation - so the same filter over the
# same data answers differently on the two database families. Reddens on PostgreSQL only, which is
# what the finding is about.
s{\$wheres\[\] = \$DB->sql_equal\(\$offered->expression, ':' \. \$name, false, true\);}{\$wheres[] = \$offered->expression . ' = :' . \$name;}s;
