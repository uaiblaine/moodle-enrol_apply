# PS7: "Moderator recruitment" takes the last offered deciding role instead of the first.
s#return \$candidates\[0\];#return end(\$candidates);#;
