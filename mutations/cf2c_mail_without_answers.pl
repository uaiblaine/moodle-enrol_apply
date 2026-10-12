# CF2C: the approver's e-mail lists the profile fields only.
s{,\n\s*\\enrol_apply\\local\\answers::submitted_values\(\$instance, \$data\)\n}{\n}s;
