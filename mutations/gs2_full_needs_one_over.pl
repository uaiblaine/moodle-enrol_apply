# GS2: a group is full only once it is OVER its seats, so the last seat is given twice.
s{if \(\(int\) \$group->members >= \$group->seats\) \{}{if ((int) \$group->members > \$group->seats) \{};
