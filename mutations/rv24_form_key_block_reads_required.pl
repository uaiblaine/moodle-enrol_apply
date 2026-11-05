# RV24: the key block reads required(), so the reviewed mode's key is not marked required.
s#\$required = groupkey::mandatory\(\$instance\);\n(\s*)\$mform = \$this->_form;#\$required = groupkey::required(\$instance);\n${1}\$mform = \$this->_form;#;
