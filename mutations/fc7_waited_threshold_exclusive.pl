# FC7: a wait threshold counts as core's duration filter compares it, at least the value.
s#"SUM\(CASE WHEN w\.wait >= "#"SUM(CASE WHEN w.wait > "#;
