# RB2: the Decided cell names the key's group of an admission from the raw join, past
# formatter::key_group()'s visibility rule.
s#\$group = self::key_group\(\$row->decidedkeygroupname \?\? null, \(object\) \[#\$group = (string) (\$row->decidedkeygroupname ?? ''); self::key_group(\$row->decidedkeygroupname ?? null, (object) [#;
