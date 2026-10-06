# DV: decide the waiting-list state by the status alone again, ahead of the queue's answer. A
# waiting-list row whose timeend has passed is then described as deferred, although the queue and
# the applicant limit hold nothing for it, while a lapsed pending row still reads as not active.
s|        if \(!queue::is_awaiting_decision\(\$userenrolment\)\) \{|        if (\$status !== ENROL_APPLY_USER_WAIT && !queue::is_awaiting_decision(\$userenrolment)) {|;
