# BD1: the role's name, already escaped, is escaped a second time on its way into the bold part.
s#get_string\('reviewoutcomerole', 'enrol_apply', self::strong\(\$rolename\)\)#get_string('reviewoutcomerole', 'enrol_apply', self::strong(s(\$rolename)))#;
