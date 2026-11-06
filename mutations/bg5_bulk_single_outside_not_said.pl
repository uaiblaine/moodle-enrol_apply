# BG5: say nothing when exactly one selected application is outside the decider's groups.
s|\$customdata\['outsidenotice'\] = \$outside === 1|\$customdata['outsidenotice'] = \$outside === -1|s;
