# PL12: the batch no longer asks for viewdetails in the course.
s#\|\| \(!groupscope::holds_in_course\('moodle/user:viewdetails', \$coursecontext\)\s*&& !groupscope::holds_in_course\('moodle/user:viewalldetails', \$coursecontext\)\)#|| false#;
