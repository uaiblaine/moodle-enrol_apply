# CF4O: deleting a controlling field leaves the conditions it controlled behind.
s{'fieldid = :fieldid OR dependsonfieldid = :dependsonfieldid'}{'fieldid = :fieldid'}s;
