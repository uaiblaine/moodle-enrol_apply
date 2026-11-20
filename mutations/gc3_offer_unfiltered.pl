# GC3: the offer is shown to the applicant without core's group visibility.
s#static fn\(stdClass \$group\): bool => groupvisibility::visible_to\(\$group, \$userid\)#static fn(stdClass \$group): bool => true#;
