# U: let a refusal be built with no reason, which renders as a blank error box - the failure
# that reads as a rendering fault rather than as a refusal.
s{\n        if \(trim\(\$reason\) === ''\) \{\n            throw new coding_exception\('A refused application must carry a reason to show the applicant\.'\);\n        \}\n\n}{\n}s;
