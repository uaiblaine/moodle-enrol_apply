# AT: swap the two capacity numbers. They answer different questions - places counts ACTIVE rows
# against customint4, applicants counts every non-expired row against customint3 - and mixing them
# is the standing hazard this plugin records. Only a row-scoped assertion can see it.
#
# The whole two-row block is rewritten in one substitution, and that is a fix rather than a style.
# The first version of this gate swapped only the two `> 0` GUARDS, which changes nothing whenever
# both numbers are set - which is every fixture that can see a swap at all - so it reddened
# NOTHING on the first full sweep it was included in. A mutation that cannot be observed proves
# nothing about the test that names it.
s#'places' => \$places > 0\n                \? get_string\('reviewofmany', 'enrol_apply', \(object\) \[\n                    'taken' => \$capacity::places_taken\(\$instance\),\n                    'total' => \$places,\n                \]\)\n                : \$nolimit,#'places' => \$places > 0\n                ? get_string('reviewofmany', 'enrol_apply', (object) [\n                    'taken' => \$capacity::applicants(\$instance),\n                    'total' => \$limit,\n                ])\n                : \$nolimit,#s;
s#'applicants' => \$limit > 0\n                \? get_string\('reviewofmany', 'enrol_apply', \(object\) \[\n                    'taken' => \$capacity::applicants\(\$instance\),\n                    'total' => \$limit,\n                \]\)\n                : \$nolimit,#'applicants' => \$limit > 0\n                ? get_string('reviewofmany', 'enrol_apply', (object) [\n                    'taken' => \$capacity::places_taken(\$instance),\n                    'total' => \$places,\n                ])\n                : \$nolimit,#s;
