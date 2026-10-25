# KS3: a valid key with no place left reads as a plain pending application: the applicant is not
# told why the key they typed did not let them in.
s|            \$state === self::PENDING && \$key->outcome === submission::KEY_NO_PLACE => self::KEY_NO_PLACE,\n||;
