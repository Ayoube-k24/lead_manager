<?php

namespace Tests\Feature\Livewire\Admin\Mailwizz;

use Livewire\Volt\Volt;
use Tests\TestCase;

class ImportManualTest extends TestCase
{
    public function test_it_can_render(): void
    {
        $component = Volt::test('admin.mailwizz.import-manual');

        $component->assertSee('');
    }
}
