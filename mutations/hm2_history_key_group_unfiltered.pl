# HM2: the history names the key group without the report formatter's visibility check.
s#\$keygroup = \$formatter::key_group\(\$prior->keygroupname, \$prior\);#\$keygroup = s((string) \$prior->keygroupname);#;
