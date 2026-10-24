# GK6: the summary keeps groups that carry no key.
s{if \(\$stored === ''\) \{\n\s*continue;\n\s*\}\n(\s*)\$id = }{\$id = }s;
