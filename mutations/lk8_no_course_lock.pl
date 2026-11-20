# LK8: the course lock is another lock type, which nothing else takes: the two writes are not held apart.
s#get_lock_factory\('enrol_apply_course'\)#get_lock_factory('enrol_apply_course_mutated')#;
