# SW6: every switched decider loses the mentor level, not only an administrator.
s#if \(is_siteadmin\(\) && \\enrol_apply\\local\\groupscope::switched#if (\\enrol_apply\\local\\groupscope::switched#;
