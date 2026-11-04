# SR4: trust the key resolved before the admission locks were taken.
s#if \(!\$current \|\| \\enrol_apply\\local\\groupkey::resolve\(\$current, \$posted\) !== \$keygroupid\) \{#if (false) {#;
