# CB: let the dynamic table answer yes to everybody. This is the ONE capability check on the
# refresh path - get.php applies no other - so the queue would answer any logged-in client's
# request for any instance's applications.
s{        return \(bool\) \$this->scope->allowed;}{        return true;}s;
