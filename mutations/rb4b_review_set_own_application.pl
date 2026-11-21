# RB4B: the reviewable set forgets the self-decision rule, so a decider would get Review on their
# own application, which can_view_application() refuses them.
s#\$notown = is_siteadmin\(\) \? \[\[\], \[\]\] : #\$notown = true ? [[], []] : #;
