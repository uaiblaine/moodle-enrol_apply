# XA65: the first name is substituted in a pass of its own, so a placeholder in it is expanded by the next.
s#return strtr\(\$content, \[#\$content = strtr(\$content, ['{firstname}' => s(\$user->firstname)]);\n        return strtr(\$content, [#;
