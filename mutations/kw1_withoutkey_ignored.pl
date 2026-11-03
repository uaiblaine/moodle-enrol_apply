# KW1: a key posted with "without the key" ticked still admits.
s#\$keygroupid = \(\$required \|\| empty\(\$data->withoutkey\)\)#\$keygroupid = true#;
