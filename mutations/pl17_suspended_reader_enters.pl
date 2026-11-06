# PL17: course access counts a suspended enrolment, which require_login($course) refuses.
s#can_access_course\(\$course, null, '', true\)#can_access_course(\$course)#;
