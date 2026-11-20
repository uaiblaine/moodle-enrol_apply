# GC20: judge the offered groups for whoever is logged in instead of the applicant.
s{groupvisibility::visible_to\(\$group, \$userid\)}{groupvisibility::visible_to(\$group, (int) \$GLOBALS['USER']->id)};
