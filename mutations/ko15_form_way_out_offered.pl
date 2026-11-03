# KO15: the form offers "without the key" on a key-only instance after a wrong key.
s#if \(groupkey::required\(\$this->get_instance\(\)\)\) \{\n(\s*)if \(\$this->is_continue\(\)#if (false) \{\n${1}if (\$this->is_continue()#;
