<?php
// config/mail.php  (NEW)
// Email settings. While 'enabled' is false, emails are NOT sent: they are saved to
// logs/emails.log.php so you can see what would have been sent.
//
// To send real emails with Gmail:
//   1. Turn on 2-Step Verification on your Google account
//   2. Create an "App password" (Google Account -> Security -> App passwords)
//   3. Put your Gmail address and the 16-letter app password below, set 'enabled' => true
return [
    'enabled'    => false,
    'host'       => 'smtp.gmail.com',
    'port'       => 587,                  // 587 = STARTTLS, 465 = SSL
    'username'   => 'yourname@gmail.com',
    'password'   => 'your-16-letter-app-password',
    'from_email' => 'yourname@gmail.com',
    'from_name'  => 'Online Exam System',
    'verify_ssl' => false,                // keep false on XAMPP/localhost; set true on a real server
];
