# GD33: "Already in this course" names the decider to a reader without enrol/apply:viewreports.
s#\$decider = \$namesdecider \&\& \(int\) \$record->decidedby > 0#\$decider = (int) \$record->decidedby > 0#;
