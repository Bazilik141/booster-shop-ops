# PAY-001 — OC4 registry fix v2

The first registry runner stopped with exit 255 before it added path rows because this host has mysqli without mysqlnd, so mysqli_result::fetch_all is unavailable.

v2 replaces only that call with a portable fetch_assoc loop. It keeps the same idempotent registry logic and safely resumes the partial state: an existing mono_chast extension_install record is updated, missing extension_path rows are added, and the marker is written only after success.

Validation: php -l passed. No extension source, settings, transaction data or existing .pay001-marker is changed.
