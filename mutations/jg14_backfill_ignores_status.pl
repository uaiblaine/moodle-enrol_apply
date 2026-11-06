# JG14: the upgrade backfills records whatever their status, a pending one included.
s#WHERE s\.status = :approved\n\s*AND s\.joinedgroups IS NULL#WHERE s.joinedgroups IS NULL#;
