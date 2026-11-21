# XA21: can_access_course() without $onlyactive, which counts a suspended enrolment as access.
s#can_access_course\(\$course, null, 'enrol/apply:viewreports', true\)#can_access_course(\$course, null, 'enrol/apply:viewreports')#;
