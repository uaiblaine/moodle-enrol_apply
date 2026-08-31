# AT: swap the two capacity numbers. They answer different questions - places counts ACTIVE rows
# against customint4, applicants counts every non-expired row against customint3 - and mixing them
# is the standing hazard this plugin records. Only a row-scoped assertion can see it.
s#'places' => \$places > 0#'places' => \$limit > 0#s;
s#'applicants' => \$limit > 0#'applicants' => \$places > 0#s;
