# PF4: never put the field's values where the type reads them.
s{\n            \$_POST\[\$name\] = \$value;\n}{\n}s;
