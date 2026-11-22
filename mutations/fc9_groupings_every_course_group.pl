# FC9: the groupings read every grouped group of the course, not the offered ones.
s#WHERE g\.courseid = :courseid AND gg\.groupid \{\$insql\}#WHERE g.courseid = :courseid AND gg.groupid > 0#;
