# CE: judge the mentee scope's identity fields in the system context. That scope spans courses in
# one statement, so there is no context that answers for every row - and the mentor holds no
# course access at all, which is the whole point of the delegation.
s{            'mentees' => \$mentees,\n            'identitycontext' => null,}{            'mentees' => \$mentees,\n            'identitycontext' => context_system::instance\(\),}s;
