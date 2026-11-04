# KA8: the end of the request forgets its throttle locks without releasing them.
s#            if \(\$lock\) \{\n                \$lock->release\(\);\n            \}#            if (\$lock) \{\n                \$lock;\n            \}#;
