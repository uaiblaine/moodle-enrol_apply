# R: keep the guard but drop the user id, so the write door judges whoever is logged in
# rather than the applicant. Fails OPEN whenever the operator is a cohort member and the
# applicant is not - a task or a web service enrolling somebody else.
s{\$this->allow_apply\(\$instance, \(int\) \$userid\)}{\$this->allow_apply(\$instance)};
