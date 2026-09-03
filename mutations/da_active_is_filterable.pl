# DA: offer ENROL_USER_ACTIVE as a status to filter by. The queue's own predicate excludes active
# enrolments by construction, so it is an option that can only ever return zero rows - and 0 is
# what PARAM_INT cleans an empty `status=` to, which is how the select's own "any status" option
# turned every search made through the form into a filter no row could satisfy.
s{        return \[ENROL_USER_SUSPENDED, ENROL_APPLY_USER_WAIT\];}{        return [ENROL_USER_ACTIVE, ENROL_USER_SUSPENDED, ENROL_APPLY_USER_WAIT];}s;
