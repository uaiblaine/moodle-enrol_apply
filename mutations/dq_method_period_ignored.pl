# DQ: ignore the enrolment method's own period, so every approved enrolment runs for ever. With
# the decision-level override deleted (2026-09-04) this is the ONLY path a period reaches an
# enrolment by, so nothing else would notice.
s{\$userenrolment->timeend = \$instance->enrolperiod\n                \? \$userenrolment->timestart \+ \$instance->enrolperiod\n                : 0;}{\$userenrolment->timeend = 0;}s;
