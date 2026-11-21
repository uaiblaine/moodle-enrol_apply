# RB4D: the Review action stops reading the set and is drawn on every row with an enrolment.
s#return \$userenrolmentid > 0 && isset\(\$this->reviewable\[\$userenrolmentid\]\);#return \$userenrolmentid > 0;#;
