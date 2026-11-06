# JG15: the upgrade rewrites records that already say what they joined, and every re-run again.
s#\n\s*AND s\.joinedgroups IS NULL##;
