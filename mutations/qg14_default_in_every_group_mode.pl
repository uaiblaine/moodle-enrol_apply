# QG14: the default applies in separate groups too, and in no groups.
s#return \(int\) groups_get_course_groupmode\(\$course\) === VISIBLEGROUPS \? self::MINE : null;#return self::MINE;#;
