# RB3: the Group cell names the chosen group from the raw join, past formatter::chosen_group()'s
# visibility rule.
s#\$name = self::chosen_group\(\$row->groupchosenname \?\? null, \(object\) \[#\$name = (string) (\$row->groupchosenname ?? ''); self::chosen_group(\$row->groupchosenname ?? null, (object) [#;
