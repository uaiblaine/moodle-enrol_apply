# PF11: put the required error on a hidden element, where nobody would see it.
s{\n                if \(\$this->_form->getElementType\(\$name\) !== 'hidden'\) \{\n                    (\$errors\[\$name\] = get_string\('requiredtoapply', 'enrol_apply'\);)\n                \}\n}{\n                $1\n}s;
