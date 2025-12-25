<?php

namespace Tests\Feature\Livewire\Owner\Leads;

use Livewire\Volt\Volt;
use Tests\TestCase;

class ReassignTest extends TestCase
{
    public function test_it_can_render(): void
    {
        $component = Volt::test('owner.leads.reassign');

        $component->assertSee('');
    }
}
