# F2I: build the missing-fields paragraph with html_writer again on the acknowledgement page.
s#echo \$PAGE->get_renderer\('enrol_apply'\)->profile_missing\(\$missing\);#echo html_writer::tag('p', get_string('profileincomplete_desc', 'enrol_apply'));\n        echo \$PAGE->get_renderer('enrol_apply')->profile_missing(\$missing);#s;
