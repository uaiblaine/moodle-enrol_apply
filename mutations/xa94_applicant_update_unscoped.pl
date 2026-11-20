# XA94: the applicant update loses its WHERE and blanks every record.
s#(decisionnote = :note\s+WHERE )userid = :userid#$1userid <> -1#;
