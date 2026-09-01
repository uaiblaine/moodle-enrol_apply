# BJ: claim every deferral is a fresh decision. decide()'s guard is then overridden on a row that
# did not move, so correcting the reason re-attributes the deferral - date and decider both - to
# whoever did the correcting.
s|                \\enrol_apply\\local\\submission::STATUS_WAITING,\n                \(int\) \$USER->id,\n                \$moved\n            \);|                \\enrol_apply\\local\\submission::STATUS_WAITING,\n                (int) \$USER->id,\n                true\n            );|s;
