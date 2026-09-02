# CF: trust check_validity() alone and cast the value straight through. A filter that is PRESENT
# with an EMPTY value list satisfies check_validity(), current() answers null for it, and (int)
# null is 0 - so a request that named no scope silently gets the widest one this queue has. The
# request is well formed against core's own service, which is what makes it reachable.
s{        \$enrolid = \$filterset->get_filter\('enrolid'\)->current\(\);\n        if \(\$enrolid === null\) \{\n            throw new \\moodle_exception\('missingrequiredfields', 'core_table', '', 'enrolid'\);\n        \}\n\n        \$this->scope = queue::listing_scope\(\(int\) \$enrolid\);}{        \$this->scope = queue::listing_scope\(\(int\) \$filterset->get_filter\('enrolid'\)->current\(\)\);}s;
