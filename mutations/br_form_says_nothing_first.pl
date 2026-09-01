# BR: drop the warning from the confirmation form, leaving only the one that arrives after the
# decision. The form is the only surface in this flow that can speak before anything is written,
# which is the difference between a chance to stop and a report.
s#        if \(!empty\(\$this->_customdata\['othernotice'\]\)\) \{\n            global \$OUTPUT;\n\n            \$mform->addElement\('static', 'bulkothermethods', '', \$OUTPUT->notification\(\n                \$this->_customdata\['othernotice'\],\n                \\core\\output\\notification::NOTIFY_WARNING,\n                false\n            \)\);\n        \}\n\n##s;
