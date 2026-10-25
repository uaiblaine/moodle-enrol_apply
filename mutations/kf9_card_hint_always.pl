# KF9: the enrolment page's card mentions the key where it would be ignored.
s#if \(\\enrol_apply\\local\\groupkey::enabled\(\$instance\)\) \{\n(\s*)\$body \.= #if (true) \{\n${1}\$body .= #;
