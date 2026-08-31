# V: drop the applicant cap from the WRITE door.
s{\n            if \(\\enrol_apply\\local\\capacity::applications_closed\(\$instance\)\) \{.*?\n            \}\n}{\n}s;
