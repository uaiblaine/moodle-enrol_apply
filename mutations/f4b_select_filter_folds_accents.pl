# F4B: compare a select filter accent-insensitively. On MySQL and MariaDB sql_equal() then drops
# the _bin collation and trusts the column's accent-insensitive one, so "Pais" also lists "País".
# PostgreSQL's LOWER() comparison is accent-sensitive either way: this reddens on MariaDB or MySQL
# only, the mirror image of DM.
s#sql_equal\(\$offered->expression, ':' \. \$name, false, true\)#sql_equal(\$offered->expression, ':' . \$name, false, false)#s;
