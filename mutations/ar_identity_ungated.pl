# AR: resolve the review page's identity against no context, which is the mentee scope's answer.
# It fails CLOSED - the line disappears for every reader, including the one entitled to see it -
# so the control test that asserts a named field IS shown is what catches this, not the one that
# asserts an unnamed field is hidden.
s#identity::values\(\$coursecontext, \(int\) \$applicant->id\)#identity::values(null, (int) \$applicant->id)#s;
