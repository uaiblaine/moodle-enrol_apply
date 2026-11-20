# FG19: no hidden constant for the card option where no cohort can be chosen.
s#\n\s*\$mform->addElement\(\x27hidden\x27, \x27hidecard\x27\);\n\s*\$mform->setType\(\x27hidecard\x27, PARAM_INT\);\n\s*\$mform->setConstant\(\x27hidecard\x27, 0\);##;
