# EV1: activate() writes the enrolment outside decisionlog::defer(), so the observer's pass of
# complete_approval(), nested in core's write, logs the approval as well.
s#\\enrol_apply\\local\\decisionlog::defer\(\n                \(int\) \$userenrolment->id,\n                fn\(\) => \$this->update_user_enrol\(#call_user_func(\n                fn() => \$this->update_user_enrol(#s;
