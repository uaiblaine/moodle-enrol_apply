# F4A: split a suspended approval on a timeend rule of the formatter's own instead of the queue's.
# This is the rule the formatter carried before it delegated: "expired" only for an end that is
# positive and strictly in the past, so an end equal to now or a negative one - which the queue
# excludes - is reported as a suspension back in the queue. The same call guards the pending and
# waiting-list branch above it (F4A2), so the pattern is anchored on the approval branch's own
# return: a bare s/// rewrites only the first occurrence, which is the other branch.
s#if \(!queue::is_awaiting_decision\(\$enrolment\)\) \{(\s*)return get_string\('outcomeexpired'#if (\$enrolment->timeend > 0 && \$enrolment->timeend < time()) {$1return get_string('outcomeexpired'#s;
