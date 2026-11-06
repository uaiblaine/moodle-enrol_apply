# RV12: the key group of an application for review is worded as an admission's.
s#\n(\s*)\$admission !== null && !\$admission->admitted\n#\n${1}false\n#;
