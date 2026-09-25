# F2J: print the queue's description with html_writer again, beside the template.
s#        echo \$this->manage_form\(\$table, \$manageurl, \$instance\);#        echo html_writer::tag('p', get_string('confirmusers_desc', 'enrol_apply'));\n        echo \$this->manage_form(\$table, \$manageurl, \$instance);#s;
