# PF9: offer a custom value that says nothing as a change, so it is written over the stored one.
s{(\$after = customfield::display\(\$field, customfield::stored_value\(\$field, \$submitted\)\);)\n        if \(\$after === ''\) \{\n            return null;\n        \}\n}{$1\n}s;
