# SC34: the queue's and review page's choosers are offered without enrol/apply:choosedecision.
s#\$chooses = \$decides && \\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:choosedecision', \$coursecontext\);#\$chooses = \$decides;#;
