# CT: judge the mask once from the SCOPE's context instead of per row. This is what the first cut
# of this column did, on a docblock claiming a system-level capability is held in every course
# below it. has_capability_in_accessdata() walks UPWARD only, so a CAP_PROHIBIT recorded at a
# course is invisible to a check made at the system context - and a site-wide operator with the
# identity capability prohibited in one course is shown every pill of that course's applicants.
# Indistinguishable from correct on any site that uses no per-course overrides.
s{        \$visible = \$this->visible_keys\(\(int\) \$row->courseid\);}{        \$visible = submissionformatter::visible_keys(\$this->scope->identitycontext);}s;
