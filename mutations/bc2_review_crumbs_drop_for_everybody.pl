# BC2: the review page drops core's automatic crumbs for every reader, one who may open the course included.
s#if \(!can_access_course\(\$course, null, '', true\)\) \{\n(\s*)\$navbar->ignore_active\(\);#if (true) {\n${1}\$navbar->ignore_active();#;
