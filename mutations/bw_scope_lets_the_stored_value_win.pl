# BW: merge with + instead of array_merge, so the STORED value wins on a duplicate key. The url's
# method then loses to whatever the reader last chose, and the scoping does nothing at all for
# anybody who has used the report twice - which is everybody, after the first visit. It was
# written this way first, and the inversion is invisible at the call site.
s#return \$this->set_filter_values\(array_merge\(\$this->get_filter_values\(\), \[#return \$this->set_filter_values(\$this->get_filter_values() + [#;
s#            self::METHOD_FILTER \. '_value' => \$enrolid,\n        \]\)\);#            self::METHOD_FILTER . '_value' => \$enrolid,\n        ]);#;
