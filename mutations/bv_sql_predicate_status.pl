# BV: drop the status half of the SQL predicate every listing in this plugin is built from. Gates
# C and D hold the OBJECT form of the same rule - is_awaiting_decision(), which the participants
# page uses - and leave every query byte-identical, so until this existed the SQL twin was held
# by no mutation at all.
s#\['ue.status != :active', '\(ue.timeend = 0 OR ue.timeend > :now\)'\],#['(ue.timeend = 0 OR ue.timeend > :now)'],#;
