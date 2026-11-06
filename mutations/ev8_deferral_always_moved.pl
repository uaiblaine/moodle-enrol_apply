# EV8: every deferral is reported as a move, so a message about an earlier deferral reads as a
# second deferral.
s#\['moved' => \$moved, 'withmessage' => \$withmessage\]#['moved' => true, 'withmessage' => \$withmessage]#;
