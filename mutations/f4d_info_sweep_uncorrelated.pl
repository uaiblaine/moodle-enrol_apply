# F4D: drop the correlation from the applicationinfo orphan sweep. NOT EXISTS over the whole of
# {user_enrolments} is false whenever any enrolment exists, so no orphan is ever deleted.
s# WHERE ue\.id = \{enrol_apply_applicationinfo\}\.userenrolmentid##s;
