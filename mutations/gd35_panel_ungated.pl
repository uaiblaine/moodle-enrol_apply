# GD35: the "Already in this course" panel without core's enrolment gate.
s{        if \(!has_capability\('moodle/course:enrolreview', \$coursecontext\)\) \{\n            return \['hasenrolments' => false, 'enrolments' => \[\]\];}{        if (false) \{\n            return ['hasenrolments' => false, 'enrolments' => []];};
