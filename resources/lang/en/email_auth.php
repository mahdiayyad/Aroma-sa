<?php

return [
    'tab_phone' => 'Mobile number',
    'tab_email' => 'Email & password',

    'email_label' => 'Email address',
    'password_label' => 'Password',
    'continue' => 'Continue',
    'back' => 'Back',
    'hint' => 'We\'ll email you a code to confirm it\'s you.',
    'sent' => 'We emailed you a verification code.',
    'verify_title' => 'Check your email',

    'errors' => [
        'invalid_credentials' => 'That email and password don\'t match our records.',
        'send_failed' => 'We couldn\'t email your code right now. Please try again in a moment, or sign in with your mobile number.',
        'session_expired' => 'Your sign-in session expired. Please enter your email and password again.',
    ],

    'mail' => [
        'subject' => [
            'login' => 'Your Aroma sign-in code',
            'add_credentials' => 'Confirm your email for Aroma',
        ],
        'intro' => [
            'login' => 'Use this code to finish signing in to your Aroma account:',
            'add_credentials' => 'Use this code to confirm this email address for your Aroma account:',
        ],
        'expires' => 'The code expires in :minutes minutes and works once.',
        'ignore' => [
            'login' => 'If this wasn\'t you, someone knows your password — please change it right away.',
            'add_credentials' => 'If you didn\'t ask for this, you can safely ignore this email.',
        ],
    ],

    'profile' => [
        'title' => 'Email & password sign-in',
        'intro_add' => 'Add an email and password so you can also sign in without your phone. We\'ll email you a code to confirm the address.',
        'email' => 'Email address',
        'password' => 'Password',
        'confirm' => 'Confirm password',
        'send_code' => 'Email me a code',
        'added' => 'Email and password sign-in is now set up.',
        'already_set' => 'Email and password sign-in is already set up.',
        'session_expired' => 'That request expired. Please start again.',
        'change_title' => 'Change password',
        'current_password' => 'Current password',
        'new_password' => 'New password',
        'save_password' => 'Update password',
        'password_changed' => 'Your password has been updated.',
        'wrong_current' => 'The current password is incorrect.',
        'signin_email' => 'Sign-in email',
        'email_locked_hint' => 'This is your sign-in email. Contact support to change it.',
    ],
];
