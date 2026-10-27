# PF7: offer a field whose type is no longer installed.
s{\n            if \(!customfield::type_available\(\(string\) \$field->datatype\)\) \{\n.*?\n            \}\n}{\n}s;
