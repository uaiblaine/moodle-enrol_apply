# KO15: the form offers "without the key" where the key is mandatory, after a wrong key.
s#if \(groupkey::mandatory\(\$this->get_instance\(\)\)\) \{\n(\s*)if \(\$this->is_continue\(\)#if (false) \{\n${1}if (\$this->is_continue()#;
