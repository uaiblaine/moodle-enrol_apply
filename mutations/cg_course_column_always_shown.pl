# CG: show the course column on every scope. An instance-scoped queue then repeats one course
# name down the page and charges the applicant's own cell the width to do it - the column says
# nothing a reader did not know from the url they followed.
s{        if \(\$this->scope->instance === null\) \{\n            \$columns\[\] = 'course';\n            \$headers\[\] = get_string\('course'\);\n        \}}{        \$columns[] = 'course';\n        \$headers[] = get_string('course');}s;
