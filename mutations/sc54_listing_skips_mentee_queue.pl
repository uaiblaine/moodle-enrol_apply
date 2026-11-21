# SC54: the listing offers a site-wide viewer who also mentors the read-only queue, not their mentees.
# Anchored on the line after the test: queue::reviewable_in_course() tests $mentees too.
s#if \(\$mentees\) \{(\s*)return \(object\) \[#if (false) {$1return (object) [#;
