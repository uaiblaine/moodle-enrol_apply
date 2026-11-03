# KO2: "without the key" is honoured on a key-only instance, refusing a valid key.
s#\$keygroupid = \(\$required \|\| empty\(\$data->withoutkey\)\)#\$keygroupid = (false || empty(\$data->withoutkey))#;
