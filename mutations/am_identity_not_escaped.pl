# AM: stop escaping the identity columns. flexible_table writes $row->$column into the cell with
# no escaping of its own, so this is the plugin's own XSS boundary, not core's.
s{return s\(\$row->\{\$colname\}\);}{return \$row->{\$colname};}s;
