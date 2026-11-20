# GD3: the per-row application group forgets the chosen group.
s#return \$choice \? max\(0, \(int\) \$row->chosengroupid\) : 0;#return 0;#;
