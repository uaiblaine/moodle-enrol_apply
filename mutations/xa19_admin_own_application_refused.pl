# XA19: a site administrator is refused their own application too.
s#return \$userid === \(int\) \$USER->id && !is_siteadmin\(\);#return \$userid === (int) \$USER->id;#;
