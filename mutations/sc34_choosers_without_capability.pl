# SC34: the queue's and review page's choosers are offered without enrol/apply:choosedecision.
s#\$chooses = \$decides && has_capability\('enrol/apply:choosedecision', \$coursecontext\);#\$chooses = \$decides;#;
