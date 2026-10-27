# PF8: offer a custom value that says the same as the stored one as a change.
s{\n        \$before = customfield::display\(\$field, customfield::current\(\$field\)\);\n        if \(\$before === \$after\) \{\n            return null;\n        \}\n}{\n        \$before = customfield::display(\$field, customfield::current(\$field));\n}s;
