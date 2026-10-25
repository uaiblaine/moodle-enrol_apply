# KS6: the key group's name reaches the notification and the exception unescaped; both render the
# body raw, so a group name is markup.
s|            format_string\(\$group->name, true, \['context' => \\context_course::instance\(\$group->courseid\)\]\),|            \$group->name,|;
