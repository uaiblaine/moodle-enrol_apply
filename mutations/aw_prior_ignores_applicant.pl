# AW: drop the applicant clause from the earlier-applications lookup, so the review page lists
# every applicant's history for the course instead of this one person's.
s#WHERE s.courseid = :courseid AND s.userid = :userid AND s.id <> :excludeid#WHERE s.courseid = :courseid AND s.id <> :excludeid#s;
