# KS16: a group exactly at its seats is warned about as overfilled after every approval into it.
s#            if \(\$seat->seats === null \|\| \(int\) \$seat->members <= \$seat->seats\) \{#            if (\$seat->seats === null || (int) \$seat->members < \$seat->seats) {#;
