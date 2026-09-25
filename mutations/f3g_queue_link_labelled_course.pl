# F3G: label the new-application notice's link "Course" although it opens the approval queue.
s#\$type === 'application' \? get_string\('applymanage', 'enrol_apply'\)#\$type === 'application' ? get_string('course')#s;
