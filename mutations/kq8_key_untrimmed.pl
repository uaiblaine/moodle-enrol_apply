# KQ8: the step reads the key as posted, so a pasted space hides a valid key.
s#return is_string\(\$key\) \? trim\(\$key\) : '';#return is_string(\$key) ? \$key : '';#;
