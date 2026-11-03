# KQ2: a valid key posted past the throttle's lock builds step 2.
s#\n\s*&& !keyattempts::locked\(\(int\) \$instance->id, \(int\) \$USER->id\)##;
