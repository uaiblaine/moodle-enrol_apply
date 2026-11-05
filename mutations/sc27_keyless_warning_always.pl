# SC27: the warning is shown whatever the course's group mode.
s#return \$course && \(int\) groups_get_course_groupmode\(\$course\) === SEPARATEGROUPS;#return true;#;
