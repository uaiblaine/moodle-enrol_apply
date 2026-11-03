# KO19: the edit form saves any value of the admission notice switch.
s#if \(!in_array\(\(string\) \(\$data\['customchar2'\] \?\? ''\), \['', \\enrol_apply\\local\\groupkey::NOTICE_ON\], true\)\) \{#if (false) \{#;
