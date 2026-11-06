# PL3: the parent branch no longer asks for moodle/user:viewdetails in the user context.
s#\n            && has_capability\('moodle/user:viewdetails', \$usercontext\)\n#\n#;
