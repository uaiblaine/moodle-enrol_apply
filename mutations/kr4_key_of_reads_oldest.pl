# KR4: key_of() answers for the oldest application of the user enrolment.
s#'timecreated DESC, id DESC',\n(\s*)'id, keygroupid, keyoutcome',#'timecreated ASC, id ASC',\n${1}'id, keygroupid, keyoutcome',#;
