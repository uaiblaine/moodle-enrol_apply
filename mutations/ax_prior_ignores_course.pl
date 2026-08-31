# AX: drop the course clause, so the panel lists this applicant's history in courses the reader
# may hold nothing in.
s#WHERE s.courseid = :courseid AND s.userid = :userid AND s.id <> :excludeid#WHERE s.userid = :userid AND s.id <> :excludeid#s;
