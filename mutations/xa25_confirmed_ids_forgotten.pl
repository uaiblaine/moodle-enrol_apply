# XA25: the decision forgets which rows the confirmation reached.
s#self::\$confirmed = array_merge\(self::\$confirmed, \$plugin->get_confirmed_enrolment_ids\(\)\);#self::\$confirmed = [];#;
