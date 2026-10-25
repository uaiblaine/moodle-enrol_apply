# KS17: a record without a key gets no admission route at all in the report.
s|        if \(\$outcome === submissionhelper::KEY_NONE\) \{\n            return get_string\('admissionroutereview', 'enrol_apply'\);\n        \}\n||;
