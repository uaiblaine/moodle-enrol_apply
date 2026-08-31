# AO: let the mentee scope resolve identity fields anyway. It spans courses in one statement, so
# no single context can judge it and a per-row mask is unsound for a sortable column.
s{        if \(\$context === null\) \{\n            return \[\];\n        \}\n\n        return fields::get_identity_fields\(\$context\);}{        return fields::get_identity_fields(\$context);}s;
