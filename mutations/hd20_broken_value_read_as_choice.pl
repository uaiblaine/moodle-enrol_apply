# HD20: a stored value that is not the shape reads as no choice.
s#array_key_exists\('columns', \$stored\) && is_array\(\$stored\['columns'\]\) \? \$stored\['columns'\] : null#array_key_exists('columns', \$stored) ? (array) \$stored['columns'] : null#;
