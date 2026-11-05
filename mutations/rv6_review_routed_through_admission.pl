# RV6: the reviewed mode falls through to the admission path (locks, then admit()).
s#\n\s*if \(!\\enrol_apply\\local\\groupkey::admits\(\$instance\)\) \{\n\s*return \$this->apply_for_review\(\$instance, \$userid, \$data, \$keygroupid, \$targets\);\n\s*\}\n#\n#;
