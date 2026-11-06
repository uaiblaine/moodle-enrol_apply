# EV6: the marker is a flag rather than a count, so activate()'s inner defer ending clears
# admit()'s outer one and the key admission is logged as an approval.
s#self::\$owed\[\$userenrolmentid\] = \(self::\$owed\[\$userenrolmentid\] \?\? 0\) \+ 1;#self::\$owed[\$userenrolmentid] = 1;#;
