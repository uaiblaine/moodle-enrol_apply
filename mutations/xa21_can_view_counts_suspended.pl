# XA21: can_access_course() without $onlyactive, which counts a suspended enrolment as access.
s#return can_access_course\(\$course, null, \$capability, true\)#return can_access_course(\$course, null, \$capability)#;
