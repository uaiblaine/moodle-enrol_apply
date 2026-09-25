# F5D: move the badge's text colour onto a wrapper. .badge sets its own color, so the wrapper's
# never reaches it; a check reading the whole line still finds text-white on it.
s#<span class="badge bg-success text-white">\{\{statustext\}\}</span>#<span class="text-white"><span class="badge bg-success">{{statustext}}</span></span>#s;
