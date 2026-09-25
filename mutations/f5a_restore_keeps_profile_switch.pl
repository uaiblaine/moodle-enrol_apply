# F5A: stop zeroing the profile-write switch on restore, so an archive that carries it on turns
# on writes to {user} in the restored course.
s{\n        \$data->customint8 = 0;\n}{\n}s;
