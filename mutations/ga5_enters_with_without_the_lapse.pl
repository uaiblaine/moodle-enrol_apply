# GA5: enters_with() answers through can_access_course() alone, which guest access satisfies for a lapsed reader.
s#return can_access_course\(\$course, null, \$capability, true\)\n\s*&& self::holds_in_course\(\$capability, context_course::instance\(\(int\) \$course->id\)\);#return can_access_course(\$course, null, \$capability, true);#;
