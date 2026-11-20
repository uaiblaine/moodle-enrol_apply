# XA13: the hook judges the plugin's own activation too, so a key admission is refused to an applicant holding enrol/apply:manage, as a self-decision.
s#if \(!local\\approvalwrite::is_own\(\$userenrolmentid\) && self::refuses_approval#if (self::refuses_approval#;
