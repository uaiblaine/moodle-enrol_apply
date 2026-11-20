# F5C: put white text on the light "awaiting a decision" badge of the review page's other
# enrolments (1.95:1). A text utility is present, so a check accepting any of them passes it; only
# the pairing catches it. Repointed from the queue's waiting-list badge, which became a status
# label of the Design System (Q1) and carries no Bootstrap background any more.
s{return \[get_string\('outcomeawaiting', 'enrol_apply'\), 'bg-warning text-dark'\];}{return [get_string('outcomeawaiting', 'enrol_apply'), 'bg-warning text-white'];}s;
