# SC14: core's edit-enrolment route changes an application the acting user may not decide.
s#if \(!local\\approvalwrite::is_own\(\$userenrolmentid\) && self::refuses_approval\(\$hook->enrolinstance, \$userenrolment\)\) \{#if (false) {#;
