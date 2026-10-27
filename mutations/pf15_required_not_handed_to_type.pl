# PF15: never hand the instance's "required" to the type, so it marks nothing required.
s{\n        if \(\$required !== null\) \{\n            \$record->required = \(int\) \$required;\n        \}\n}{\n}s;
