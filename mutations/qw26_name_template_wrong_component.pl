# QW26: the applicant sort reads the language's name template from a component that does not hold it.
s#get_string\('fullnamedisplay', 'moodle'\)#get_string('fullnamedisplay', 'langconfig')#;
