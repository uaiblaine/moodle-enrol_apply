# KW14: whatever is posted as the key is cast to a string, an array included.
s#\$posted = \(isset\(\$data->groupkey\) && is_string\(\$data->groupkey\)\) \? \$data->groupkey : '';#\$posted = (string) (\$data->groupkey ?? '');#;
