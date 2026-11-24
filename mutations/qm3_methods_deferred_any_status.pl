# QM3: a method's deferred count reads every status but the deferred one.
s#SUM\(CASE WHEN ue\.status = :methodsdeferred THEN 1 ELSE 0 END\) AS deferredcount#SUM(CASE WHEN ue.status <> :methodsdeferred THEN 1 ELSE 0 END) AS deferredcount#;
