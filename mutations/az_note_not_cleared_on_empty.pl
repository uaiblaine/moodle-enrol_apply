# AZ: restore the early return on an empty note, making a stored note sticky. A re-queued
# application - which core's "Edit enrolment" screen and an expiredaction of suspend both
# produce - would then be decided a second time carrying the first decision's reason.
s{        \$clean = trim\(\$note\);\n}{        \$clean = trim(\$note);\n        if (\$clean === '') {\n            return;\n        }\n};
