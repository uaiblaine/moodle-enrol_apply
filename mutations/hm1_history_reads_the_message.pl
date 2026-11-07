# HM1: the earlier applications read the message written to the applicant into the note's slot.
# The course report does not show that message, so neither may the review page's history.
s#s\.timedecided, s\.decidedby, s\.decisionnote,#s.timedecided, s.decidedby, s.outcomemessage AS decisionnote,#;
