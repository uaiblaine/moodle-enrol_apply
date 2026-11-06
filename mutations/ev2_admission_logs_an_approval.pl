# EV2: admit() activates outside decisionlog::defer(), so a key admission, which nobody decided,
# is logged as an approval beside application_admitted_by_key.
s#\\enrol_apply\\local\\decisionlog::defer\(\n                \$userenrolmentid,\n                fn\(\) => \$this->activate\(#call_user_func(\n                fn() => \$this->activate(#s;
