# DT: offer the course and category filters on every scope. On a queue already scoped to one
# enrolment method the control filters a set of one; on the mentee queue it is a control over
# courses a mentor did not ask about. Worse than noise: a value arriving from a stale url would
# then narrow a queue whose scope never offered it.
s{return \$listing->instance === null && \$listing->mentees === null;}{return true;}s;
