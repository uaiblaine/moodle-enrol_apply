# CW: filter on the durable record's status instead of the enrolment's. The two disagree on a real
# trajectory - the record is only moved to APPROVED on a transition to ENROL_USER_ACTIVE, so an
# approved participant later suspended from the participants page carries APPROVED on the record
# and SUSPENDED on the enrolment. That row answers to neither filter option and vanishes from a
# filtered queue while sitting plainly in the unfiltered one.
s{            \$wheres\[\] = 'ue\.status = :statusfilter';}{            \$wheres[] = 's.status = :statusfilter';}s;
