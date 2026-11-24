# QM1: the "By course" view answers every reader of the wide queue, a mentor included.
s#(\$listing = self::require_listing\(0\);\n)\s*if \(!coursefilter::offered\(\$listing\)\) \{\n\s*throw new \\required_capability_exception\(\$listing->context, groupscope::DECIDE, 'nopermissions', ''\);\n\s*\}\n#$1#;
