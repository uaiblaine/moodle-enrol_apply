# CA: drop the check_validity() call, so "required" in applications_filterset becomes a claim
# nothing enforces. get.php never calls it, so a request omitting enrolid would then reach
# get_filter() and die naming an array key - or, worse for a later optional filter, be taken as
# the widest scope there is. Zero is a meaningful scope here, which is why silence is the danger.
s{        \$filterset->check_validity\(\);\n\n}{}s;
