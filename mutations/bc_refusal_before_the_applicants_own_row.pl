# BC: ask allow_apply() before the applicant's own row again. The moment a method stops taking
# applications - the window closing, the instance being disabled, the cohort changing -
# everybody who has already applied is told "Enrolment is disabled or inactive", which is a
# message about somebody else's problem and says nothing about what they are waiting on.
s|\$allowapply = \$ownrow \? true : \$this->allow_apply\(\$instance\);\n\n        if \(\$ownrow\) \{|\$allowapply = \$this->allow_apply(\$instance);\n\n        if (\$allowapply !== true) {\n            \$body = \$allowapply;\n        } else if (\$ownrow) {|s;
