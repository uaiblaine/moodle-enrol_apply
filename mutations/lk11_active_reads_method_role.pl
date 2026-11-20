# LK11: judge an active enrolment by its method's role instead of the role its approval stamped.
s{                    if \(\$stamped\) \{\n                        return true;}{                    if (\\enrol_apply\\local\\decidingrole::method_decides(\$row)) \{\n                        return true;};
