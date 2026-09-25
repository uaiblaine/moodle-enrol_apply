# F1F: freeze every field of the site pool the applicant submitted, rather than the ones the
# instance asks for. A snapshot must record the questions that were asked.
s#        foreach \(self::resolve\(\$instance\)->keys\(\) as \$key\) \{#        foreach (self::pool() as \$key) {#;
