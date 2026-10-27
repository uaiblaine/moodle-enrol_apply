# PF6: classify a field whose type is no longer installed as if it could be drawn.
s{if \(!\$field \|\| !customfield::type_available\(\(string\) \$field->datatype\)\) \{}{if (!\$field) \{}s;
