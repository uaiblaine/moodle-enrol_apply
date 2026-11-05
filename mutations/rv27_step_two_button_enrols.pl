# RV27: step 2 of the reviewed mode on the page is submitted with "Enrol me".
s#\$step === 2 \? \$this->step_two_label\(\$instance\) : get_string#\$step === 2 ? get_string('enrolme', 'core_enrol') : get_string#;
