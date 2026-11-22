# PG1: the page's whole rows are kept in the order the second statement returned them.
s#array_values\(array_filter\(array_map\(fn\(int \$id\) => \$rows\[\$id\] \?\? null, \$ids\)\)\)#array_values(\$rows)#;
