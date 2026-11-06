# EV3: complete_approval() logs on every pass, whatever the marker says.
s#if \(!\\enrol_apply\\local\\decisionlog::is_owed\(\(int\) \$userenrolmentid\)\) \{#if (true) {#;
