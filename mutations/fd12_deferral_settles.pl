# FD12: a deferral counts as the resolution.
s#\$settled = submissionhelper::STATUS_APPROVED \. #\$settled = submissionhelper::STATUS_WAITING . ", " . submissionhelper::STATUS_APPROVED . #;
