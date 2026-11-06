# QG21: the line's names are escaped before the template escapes them again.
s#(\$names\[\] = format_string\(\$group->name, true, \['context' => \$context, 'escape' => )false\]\);#${1}true]);#;
