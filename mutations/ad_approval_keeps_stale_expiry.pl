# AD: let an approval inherit an expiry the row was already carrying. With no enrolperiod on
# the instance nothing else overwrites it, so a past date survives and the applicant ends up
# ACTIVE with no access - and under the shipped expiredaction of KEEP nothing corrects it.
#
# Anchored on the comment above, because the assignment alone also appears in wait_enrolment().
s{them would give the report two sources that can disagree\. \*/\n            \$userenrolment->timestart = time\(\);\n            \$userenrolment->timeend = 0;\n}{them would give the report two sources that can disagree. */\n            \$userenrolment->timestart = time();\n}s;
