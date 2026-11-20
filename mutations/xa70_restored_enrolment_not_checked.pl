# XA70: a record keeps the user enrolment its archive names without checking whose it is.
s#&& !\$DB->record_exists\('user_enrolments', \['id' => \$userenrolmentid, 'enrolid' => \$enrolid, 'userid' => \$userid\]\)#&& false#;
