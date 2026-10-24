# GS3: a group declaring no seats is judged as if it declared zero.
s{if \(\$group->seats === null\) \{\n\s*continue;\n\s*\}\n}{}s;
