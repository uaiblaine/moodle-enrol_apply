# XA56: the queue's unlinked applicant name is unescaped.
s#html_writer::span\(s\(fullname\(\$user\)\), 'enrol_apply-applicantname'\)#html_writer::span(fullname(\$user), 'enrol_apply-applicantname')#;
