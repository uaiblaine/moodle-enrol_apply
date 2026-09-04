# AD: let an approval inherit an expiry the row was already carrying, by not writing timeend at
# all. With no enrolperiod on the instance nothing else overwrites it, so a past date survives and
# the applicant ends up ACTIVE with no access - and under the shipped expiredaction of KEEP
# nothing corrects it.
#
# Anchored on the timestart line above it, because the assignment alone also appears in
# wait_enrolment().
#
# REPOINTED 2026-09-04: the previous pattern named the old comment text and a bare
# `$userenrolment->timeend = 0;`, both of which went when the decision-level period override was
# deleted and timeend became a ternary. It matched nothing and so guarded nothing - caught by the
# full --dry-run in the same change, which is the whole reason that run is cheap.
#
# Distinct from DQ below: this one writes NO timeend, so a stale one survives; DQ writes 0, which
# still clears a stale expiry but loses the method's period.
s{\$userenrolment->timestart = time\(\);\n            \$userenrolment->timeend = \$instance->enrolperiod\n                \? \$userenrolment->timestart \+ \$instance->enrolperiod\n                : 0;}{\$userenrolment->timestart = time();}s;
