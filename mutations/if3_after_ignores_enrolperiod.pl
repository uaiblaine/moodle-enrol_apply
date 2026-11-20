# IF3: After approval stays closed over an enrolment duration.
s#'sectionafter' => !empty\(\$instance->enrolperiod\)\n(\s*)\|\| #'sectionafter' => false\n${1}|| #;
