# KS14: the group facts stop reading seats, so a decider approves into a full group unaware. The
# queue's chooser and the review page's cards both read group_facts() (repointed for slice V3, moved to local\groupfacts for V4).
s|            \$hasseats = \$seat !== null && \$seat->seats !== null;|            \$hasseats = false;|;
