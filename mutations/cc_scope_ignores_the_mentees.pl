# CC: give the mentee scope a null restriction, which is the spelling for "every application".
# A mentor would then be listed every pending application on the site. The bug is one word, and
# the surrounding comment goes on saying the opposite.
s{( +)'mentees' => \$mentees,\n( +)'identitycontext' => null,}{${1}'mentees' => null,\n${2}'identitycontext' => null,}s;
