# PF12: draw a custom field as a text box over its stored column again.
s{            customfield::add_to_form\(\$mform, \$field\);\n}{            \$mform->addElement('text', \$name, \$label);\n            \$mform->setType(\$name, PARAM_TEXT);\n}s;
