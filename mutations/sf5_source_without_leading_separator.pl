# SF5: the source spelt with a leading separator escapes the cut.
s/\n\s*\$sources\[\] = .\\\\. \. strtolower\(\$source\);//;
