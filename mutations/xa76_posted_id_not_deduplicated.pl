# XA76: a posted id is decided once per time it is posted.
s#array_values\(array_unique\(array_map\('intval', array_values\(\$userenrolmentids\)\)\)\)#array_map('intval', array_values(\$userenrolmentids))#;
