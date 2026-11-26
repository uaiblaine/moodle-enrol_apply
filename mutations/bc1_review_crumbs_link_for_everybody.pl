# BC1: the review page keeps core's automatic crumbs, linking the course and its category, for every reader.
s#if \(!can_access_course\(\$course, null, '', true\)\) \{\n(\s*)\$navbar->ignore_active\(\);#if (false) {\n${1}\$navbar->ignore_active();#;
