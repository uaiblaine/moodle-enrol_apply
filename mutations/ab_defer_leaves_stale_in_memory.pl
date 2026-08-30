# AB: leave the in-memory row carrying the expiry that was just cleared, so the wait mail
# prints a date that no longer exists.
#
# Anchored on the comment above it, NOT on the assignment alone: lib.php carries
# `$userenrolment->timeend = 0;` twice, and the other one is in confirm_enrolment(), where it
# is immediately overwritten when the instance has an enrolperiod. The first draft of this
# pattern hit that one instead - it mutated a line no test holds, reddened nothing, and read
# exactly like a finding about this guard.
s{print the expiry that was just cleared\. \*/\n            \$userenrolment->timeend = 0;\n}{print the expiry that was just cleared. */\n}s;
