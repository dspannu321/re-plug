<?php
require_once __DIR__ . '/mailer.php';

$ok = replug_send_mail('dspannu321@gmail.com', 'Test User', 'RePlug test email', '<p>Hello from RePlug via Brevo.</p>');

echo $ok ? 'Sent!' : 'Failed.';