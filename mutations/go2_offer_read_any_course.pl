# GO2: read the offer without joining the group on the method's own course.
s{JOIN \{groups\} g ON g\.id = o\.groupid AND g\.courseid = e\.courseid}{JOIN \{groups\} g ON g.id = o.groupid};
