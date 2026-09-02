# BX: replace the reader's filters instead of merging into them. set_filter_values() overwrites
# everything it is given, so their status or date filter is silently reset every time they open
# the report from a method's icon - on a page they arrived at by clicking, which is the worst
# place to lose a choice they made.
s#array_merge\(\$this->get_filter_values\(\), \[\n            self::METHOD_FILTER \. '_operator' => select::EQUAL_TO,\n            self::METHOD_FILTER \. '_value' => \$enrolid,\n        \]\)#[\n            self::METHOD_FILTER . '_operator' => select::EQUAL_TO,\n            self::METHOD_FILTER . '_value' => \$enrolid,\n        ]#s;
