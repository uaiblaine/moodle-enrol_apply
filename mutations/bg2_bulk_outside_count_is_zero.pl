# BG2: count no application as outside the decider's groups. The confirmation form would then
# promise nothing while the decision silently leaves those rows alone.
s|&& !\$this->plugin->can_manage_application\(\$courseid, \(int\) \$userid, \(int\) \$enrolment->id\)|&& false|s;
