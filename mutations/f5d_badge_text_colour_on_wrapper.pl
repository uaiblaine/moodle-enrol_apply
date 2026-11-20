# F5D: move the badge's text colour onto a wrapper. .badge sets its own color, so the wrapper's
# never reaches it; a check reading the whole line still finds text-dark on it. Repointed from the
# capacity panel's open/closed badge, which became a status label of the Design System (Q1).
s#<span class="badge bg-warning text-dark">\{\{\#str\}\} groupkeyadmitsnobody, enrol_apply \{\{/str\}\}</span>#<span class="text-dark"><span class="badge bg-warning">{{\#str}} groupkeyadmitsnobody, enrol_apply {{/str}}</span></span>#s;
