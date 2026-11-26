# KP1: the reviewed mode's pending count reads a bare keygroupid on any record again.
s#\. ' WHERE s\.userenrolmentid = ue\.id AND s\.keyoutcome <> ' \. \\enrol_apply\\local\\submission::KEY_NONE\n.*?newer\.id > s\.id\)\)\)\)';#. ' WHERE s.userenrolmentid = ue.id AND s.keygroupid <> 0)';#s;
