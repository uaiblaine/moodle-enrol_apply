# GC5: seats count whatever the method's groupseats option says.
s#\$useseats = \(int\) instanceoptions::get\(\(int\) \$instance->id\)->groupseats === 1;#\$useseats = true;#;
