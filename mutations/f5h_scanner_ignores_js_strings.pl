# F5H: stop skipping JavaScript string literals, so a "/*" in a string opens a comment that hides
# the markup after it, and the "//" of a url drops the rest of its line.
s{'~\(' \. \$strings \. '\)\|/}{'~(?!)(' . \$strings . ')|/}s;
