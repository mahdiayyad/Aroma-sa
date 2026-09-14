<?php

return [
    'title' => 'Customer Reviews',
    'write_review' => 'Write a Review',
    'based_on' => 'Based on :count reviews',
    'based_on_one' => 'Based on 1 review',
    'no_reviews' => 'No reviews yet — be the first to share your thoughts.',
    'verified_purchase' => 'Verified Purchase',

    'form' => [
        'rating' => 'Your rating',
        'body' => 'Your review',
        'body_placeholder' => 'Write your review...',
        'submit' => 'Submit Review',
        'already_reviewed' => 'You\'ve already reviewed this product. Thank you!',
        'sign_in_prompt' => 'Sign in to write a review.',
    ],

    'submitted' => 'Thank you! Your review has been posted.',

    'errors' => [
        'already_reviewed' => 'You\'ve already reviewed this product.',
    ],

    // Used by the admin moderation panel (resources/views/admin/reviews) —
    // the moderation queue itself is unchanged, only the customer-facing
    // submission flow (now auto-approved) and display were simplified.
    'status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'hidden' => 'Hidden',
    ],
];
