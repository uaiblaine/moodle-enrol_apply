# F2D: stop handing the queue's description to the template, which is where it now renders.
s#            'descriptiontext' => get_string\('confirmusers_desc', 'enrol_apply'\),\n##s;
