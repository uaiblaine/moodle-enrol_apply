# BL: ignore the access fact, so every ACTIVE row reads as "approved with no access". A fully
# enrolled participant opening applied.php is then told their enrolment is broken and sent to
# their teacher - a falsehood, and one the row alone cannot rule out.
s|ENROL_USER_ACTIVE => \$hasaccess \? self::APPROVED : self::INACTIVE,|ENROL_USER_ACTIVE => self::INACTIVE,|;
