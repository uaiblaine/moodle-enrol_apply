# CK: drop the clause excluding the row's OWN record from the earlier-applications test. Every
# application is then evidence of itself, so every row is badged "Applied before" and the badge
# stops meaning anything - while looking, on a fixture where everyone has applied once, exactly
# like a badge that works.
# Re-pointed when the earlier applications' subquery moved to applications::prior_sql().
s{\n\s*AND \(s\.id IS NULL OR prior\.id <> s\.id\)\n}{\n}s;
