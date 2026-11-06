# KW1: a key posted with "without the key" ticked still admits.
s#\$keygroupid = \(\$mandatory \|\| empty\(\$data->withoutkey\)\)#\$keygroupid = true#;
