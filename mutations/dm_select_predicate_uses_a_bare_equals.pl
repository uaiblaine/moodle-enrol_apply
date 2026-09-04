# DM: compare a closed vocabulary with a bare `=`. moodle_database::sql_equal() emits exactly that
# for PostgreSQL, where it is case sensitive, while the mysqli driver forces COLLATE <family>_bin
# to reach the same behaviour - so the same filter over the same data answers differently on the
# two database families. Reddens on PostgreSQL only, which is what the finding is about.
s{\$wheres\[\] = \$DB->sql_equal\(\$offered->expression, ':' \. \$name, false, false\);}{\$wheres[] = \$offered->expression . ' = :' . \$name;}s;
