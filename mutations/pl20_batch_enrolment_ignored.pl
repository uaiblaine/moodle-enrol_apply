# PL20: the batch trusts its caller that every user is enrolled in the course.
s#            \$ids = array_map\('intval', \$DB->get_fieldset_sql\(#            \$enrolled = array_map('intval', \$DB->get_fieldset_sql(#;
