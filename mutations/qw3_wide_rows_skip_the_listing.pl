# QW3: rows() asks the listing question for a method's queue and trusts the caller for the wide one.
s#(function rows\(int \$enrolid, array \$options\): array \{\n)(\s*)self::require_listing\(\$enrolid\);#$1$2if (\$enrolid !== 0) {\n$2    self::require_listing(\$enrolid);\n$2}#;
