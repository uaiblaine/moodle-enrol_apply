# RV36: the label step 2 carries (attribute and page button) is "Enrol me" in every mode.
s#return groupkey::admits\(\$instance\)\n(\s*)\? get_string\('enrolme', 'core_enrol'\)#return true\n${1}? get_string('enrolme', 'core_enrol')#;
