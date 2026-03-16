<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrivacyPageTest extends TestCase
{
    public function test_privacy_page_is_accessible_and_receives_shared_contact_data(): void
    {
        $response = $this->get(route('privacy'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('privacy')
                ->where('name', config('app.name'))
                ->where('support_email', config('mail.from.address')));
    }
}
