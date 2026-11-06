# KS12: the review page names the key group to a reader who may not see the course's groups.
# Repointed for slice V3: the gate is reads_course_groups(), a decider or a viewer in the course.
s#\n            \|\| !self::reads_course_groups\(\$coursecontext\)##;
