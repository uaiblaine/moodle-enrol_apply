# PL12: the batch no longer asks for viewdetails in the course.
s#\|\| \(!has_capability\('moodle/user:viewdetails', \$coursecontext\)\s*&& !has_capability\('moodle/user:viewalldetails', \$coursecontext\)\)#|| false#;
