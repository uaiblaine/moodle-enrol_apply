# GD9: an approval without a recorded choice forgets the chosen group, and the review page does not tick it.
s#\$routegroup = \$chosen \? \$chosen->groupid : 0;#\$routegroup = 0;#;
