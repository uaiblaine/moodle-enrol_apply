# XA40: the write classifies the user object it was handed instead of the row it reads again.
s#\$user = \$DB->get_record\('user', \['id' => \(int\) \$user->id\]\);#\$user = clone \$user;#;
