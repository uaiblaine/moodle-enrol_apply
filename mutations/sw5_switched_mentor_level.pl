# SW5: a switched administrator still passes the mentor level as themselves, outside the course.
s#        if \(is_siteadmin\(\) && \\enrol_apply\\local\\groupscope::switched\(\(int\) \$courseid\)\) \{\n            return false;\n        \}\n##;
