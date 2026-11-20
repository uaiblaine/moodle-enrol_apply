# GC9: "no group" is accepted where a group is required.
s#'refusal' => \$mode === instanceoptions::CHOICE_REQUIRED \? get_string\('chosengrouprequired', 'enrol_apply'\) : null,#'refusal' => null,#;
