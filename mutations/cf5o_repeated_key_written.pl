# CF5O: a key repeated within one snapshot is written twice.
s{if \(\$record === null \|\| isset\(\$records\[\$record->fieldkey\]\)\) \{\n(\s*)continue;\n\s*\}\n(\s*)\$records\[\$record->fieldkey\] = \$record;}{if (\$record === null) {\n${1}continue;\n${2}}\n${2}\$records[] = \$record;}s;
