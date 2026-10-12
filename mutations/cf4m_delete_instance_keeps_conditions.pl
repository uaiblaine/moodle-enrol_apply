# CF4M: deleting an instance leaves its conditions between shared fields behind.
s{\$DB->delete_records\(\\enrol_apply\\local\\conditions::TABLE, \['enrolid' => \$enrolid\]\);}{}s;
