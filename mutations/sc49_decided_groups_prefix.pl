# SC49: the decided groups are matched without their separators, so group 1 matches 11.
s#\$params\[\$name\] = '%,' \. \$groupid \. ',%';#\$params[\$name] = '%' . \$groupid . '%';#;
