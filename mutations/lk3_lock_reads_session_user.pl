# LK3: the lock reads whoever is logged in instead of the applicant passed.
s#holds_deciding_application\(\$instance, \$userid\)#holds_deciding_application(\$instance, (int) \$GLOBALS['USER']->id)#;
