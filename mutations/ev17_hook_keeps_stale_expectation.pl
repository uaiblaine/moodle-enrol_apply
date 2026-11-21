# EV17: the hook keeps an expectation an earlier write left unconsumed, so a later date edit of the active enrolment approves it again.
s#        local\\approvalwrite::forget\(\$userenrolmentid\);\n##;
