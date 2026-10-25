# KS1: an admission by key reads as an ordinary approval, which claims a review nobody made.
s|            \$state === self::APPROVED && submission::admitted_by_key\(\$key\) => self::ADMITTED,\n||;
