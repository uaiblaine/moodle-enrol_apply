# SC23: the per-row check reads the oldest record of the user enrolment, not the newest.
s#'timecreated DESC, id DESC',\n(\s*)'id, keygroupid, keyoutcome' \.#'timecreated ASC, id ASC',\n${1}'id, keygroupid, keyoutcome' .#;
