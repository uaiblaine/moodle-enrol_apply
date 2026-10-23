# KA3: every wrong key restarts the window, so a slow guesser is never let back in.
s{\$entry->count\+\+;}{\$entry->count++;\n        \$entry->since = \$now;};
