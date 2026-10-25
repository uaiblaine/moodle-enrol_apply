# KW1: a key posted with "without the key" ticked still admits.
s#\$keygroupid = empty\(\$data->withoutkey\)\n(\s*)\? #\$keygroupid = true\n${1}? #;
