# JG16: the upgrade backfills a record whose enrolment is gone.
s#\n\s*JOIN \{user_enrolments\} ue ON ue\.id = s\.userenrolmentid AND ue\.enrolid = s\.enrolid AND ue\.userid = s\.userid##;
