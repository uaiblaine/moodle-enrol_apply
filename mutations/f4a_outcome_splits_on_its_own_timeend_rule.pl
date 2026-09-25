# F4A: split a suspended approval on a timeend rule of the formatter's own instead of the queue's.
# This is the rule the formatter carried before it delegated: "expired" only for an end that is
# positive and strictly in the past, so an end equal to now or a negative one - which the queue
# excludes - is reported as a suspension back in the queue.
s#if \(!queue::is_awaiting_decision\(\$enrolment\)\) \{#if (\$enrolment->timeend > 0 && \$enrolment->timeend < time()) {#s;
