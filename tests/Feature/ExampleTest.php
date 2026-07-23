<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The bare root redirects to the resolved locale prefix (e.g. /ar).
     */
    public function test_root_redirects_to_a_locale(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/ar');
    }
}
