# IF18: the mode is read loosely, so an unknown value a restore carried in reads as a key mode.
s#if \(!\\enrol_apply\\local\\groupkey::enabled\(\$instance\)\) \{#if ((\$instance->customchar1 ?? '') === '') {#;
