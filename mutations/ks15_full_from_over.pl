# KS15: a group at exactly its seats is not called full in the chooser.
s|                    \$name = \(int\) \$seat->members >= \$seat->seats|                    \$name = (int) \$seat->members > \$seat->seats|;
