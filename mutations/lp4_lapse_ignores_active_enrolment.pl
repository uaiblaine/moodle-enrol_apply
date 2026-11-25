# LP4: any enrolment reads as lapsed, an active one included.
s#\n\s*&& !is_enrolled\(\$context, \$userid, '', true\);#;#;
