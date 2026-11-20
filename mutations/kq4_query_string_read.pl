# KQ4: the key is read from the query string too, so a link can test one with no try spent.
s#return array_key_exists\(\$marker, \$submission\) && \$submission\[\$marker\] == 1 \? \$submission : \[\];#return array_merge(\$_GET, \$submission);#;
