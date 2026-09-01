# BH: stop showing the note the last decider left, so a colleague opening a deferred
# application reads that it is deferred and never why. That is the whole reason the column
# exists, and it is the half no test of the writer can see.
s{'hasdecisionnote' => \$note !== '',}{'hasdecisionnote' => false,};
