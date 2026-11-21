# XA55: the queue's linked applicant name is unescaped.
s#                s\(fullname\(\$user\)\),\n                \['class' => 'enrol_apply-applicantname'\]#                fullname(\$user),\n                ['class' => 'enrol_apply-applicantname']#s;
