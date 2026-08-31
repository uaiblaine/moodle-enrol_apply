# Y: let one more applicant in than the limit allows.
s{return self::applicants\(\$instance\) >= \$limit;}{return self::applicants(\$instance) > \$limit;};
