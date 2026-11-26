# PN1: the panel judges another application by its status and record alone, so a lapsed one reads as awaiting.
s#if \(\$instance->enrol === 'apply' && \$status !== ENROL_USER_ACTIVE && !\\enrol_apply\\local\\queue::is_awaiting_decision\(\$ue\)\) \{#if (false) {#;
