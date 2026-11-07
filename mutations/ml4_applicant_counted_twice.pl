# ML4: the applicant limit adds this application on top of a count that already holds it.
s#                    max\(0, \$capacity\['held'\] - 1\),#                    \$capacity['held'],#;
