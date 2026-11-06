# AT: swap the two capacity numbers. They answer different questions - places counts ACTIVE rows
# against customint4, applicants counts every non-expired row against customint3 - and mixing them
# is the standing hazard this plugin records. Only an assertion on the sentence that labels each
# number can see it.
#
# Mutate the VALUES, never the guards around them: swapping only the two `> 0` tests changes
# nothing whenever both numbers are set, which is every fixture able to see a swap at all, so the
# first version of this gate reddened NOTHING. Repointed for slice V3, where capacity_context()
# returns the numbers and the places signal and the method line word them.
s#'placestaken' => \$places > 0 \? \$capacity::places_taken\(\$instance\) : 0,\n            'limit' => \$limit,\n            'held' => \$limit > 0 \? \$capacity::applicants\(\$instance\) : 0,#'placestaken' => \$places > 0 ? \$capacity::applicants(\$instance) : 0,\n            'limit' => \$limit,\n            'held' => \$limit > 0 ? \$capacity::places_taken(\$instance) : 0,#s;
