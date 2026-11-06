# PL6: the review page prints the profile link whatever the reader may open.
s#\{\{\#hasprofile\}\}(<a class="small" href="\{\{profileurl\}\}">\{\{profilelabel\}\}</a>)\{\{/hasprofile\}\}#$1#;
