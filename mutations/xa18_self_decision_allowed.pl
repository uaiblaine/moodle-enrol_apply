# XA18: can_manage_application() lets a decider decide their own application.
s#if \(\\enrol_apply\\local\\queue::is_own_application\(\(int\) \$userid\)\) \{#if (false) {#;
