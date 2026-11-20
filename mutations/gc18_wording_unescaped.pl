# GC18: the chosen group's name reaches the raw sinks unescaped.
s#            self::CHOSEN,\n            format_string\(\$group->name, true, \['context' => \\context_course::instance\(\$group->courseid\)\]\),#            self::CHOSEN,\n            format_string(\$group->name, true, ['context' => \\context_course::instance(\$group->courseid), 'escape' => false]),#;
