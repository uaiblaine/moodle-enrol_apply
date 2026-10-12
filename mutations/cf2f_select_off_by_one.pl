# CF2F: the select freezes the option after the one chosen (core stores a 1-based position).
s{isset\(\$options\[\$index - 1\]\)\) \? \$plain\(\$options\[\$index - 1\]\)}{isset(\$options[\$index])) ? \$plain(\$options[\$index])}s;
