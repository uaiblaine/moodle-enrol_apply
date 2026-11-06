# JG3: the memberships read are every one of the user's in the course, stamped by this method or not.
s#WHERE gm\.userid = :userid AND gm\.component = :component AND gm\.itemid = :enrolid#WHERE gm.userid = :userid#;
