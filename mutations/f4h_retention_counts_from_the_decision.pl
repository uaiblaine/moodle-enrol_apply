# F4H: count retention from the later of submission and decision, the alternative purge()'s
# docblock rejects. A record decided moments before the sweep would then be kept for a whole
# retention period after the decision, longer than the administrator set.
s#WHERE s\.timecreated < :cutoff AND#WHERE GREATEST(s.timecreated, s.timedecided) < :cutoff AND#s;
