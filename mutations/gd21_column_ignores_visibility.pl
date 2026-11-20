# GD21: the report's "Group chosen" names a group hidden from the reader.
s#        if \(!\\enrol_apply\\local\\groupvisibility::visible_to\(\$group, \(int\) \$USER->id\)\) \{\n            return '';\n        \}\n\n(        return format_string\(\(string\) \$value, true, \[\n            'context' => \\context_course::instance\(\(int\) \$row->chosengroupcourseid\),)#$1#;
