# LP5: "no active enrolment" alone reads as lapsed, so a reader enrolled nowhere is refused.
s#\n\s*&& is_enrolled\(\$context, \$userid, '', false\)##;
