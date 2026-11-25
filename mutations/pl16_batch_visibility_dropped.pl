# PL16: the batch counts a shared group whose membership the reader may not see.
s#if \(!groupscope::holds_in_course\('moodle/course:viewhiddengroups', \$coursecontext\)\) \{#if (false) {#;
