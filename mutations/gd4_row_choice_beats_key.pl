# GD4: the per-row application group prefers a chosen group to a key route.
s#(        if \(\(int\) \$row->keyoutcome !== submission::KEY_NONE\) \{)#        if (\$choice \&\& (int) \$row->chosengroupid > 0) {\n            return (int) \$row->chosengroupid;\n        }\n$1#;
