<?php

namespace App\Policies;

use App\Models\ArticleReview;
use App\Models\User;

class ArticleReviewPolicy
{
    public function view(User $user, ArticleReview $review): bool
    {
        return $user->id === $review->user_id;
    }

    public function update(User $user, ArticleReview $review): bool
    {
        return $this->view($user, $review);
    }

    public function delete(User $user, ArticleReview $review): bool
    {
        return $this->view($user, $review);
    }
}
