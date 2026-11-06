# F4A2: split a pending or waiting-list record on a timeend rule of the formatter's own instead of
# the queue's, so a negative end (which a restore can carry, and the queue excludes) reads as still
# awaiting a decision. Anchored on this branch's own return, as F4A is on the approval branch's.
s#if \(!queue::is_awaiting_decision\(\$enrolment\)\) \{(\s*)return get_string\('outcomelapsed'#if (\$enrolment->timeend > 0 && \$enrolment->timeend < time()) {$1return get_string('outcomelapsed'#s;
