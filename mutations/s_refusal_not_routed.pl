# S: drop the refusal branch, so every outcome goes to the acknowledgement page again -
# which is the defect this change removed: a refusal wrote no enrolment row, so applied.php's
# own gate turned it into a bare "Invalid access detected".
s{\n        if \(\$result->is_refusal\(\)\) \{.*?\n        \}\n}{\n}s;
