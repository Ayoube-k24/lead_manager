<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadNoteService;

beforeEach(function () {
    require_once __DIR__.'/../../Feature/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->service = app(LeadNoteService::class);
});

test('can create a note for a lead', function () {
    $lead = Lead::factory()->create();
    $user = User::factory()->create();
    $content = 'This is a test note';

    $note = $this->service->createNote($lead, $user, $content);

    expect($note)
        ->toBeInstanceOf(LeadNote::class)
        ->and($note->lead_id)->toBe($lead->id)
        ->and($note->user_id)->toBe($user->id)
        ->and($note->content)->toBe($content)
        ->and($note->is_private)->toBeFalse()
        ->and($note->type)->toBe('comment');
});

test('can create a private note', function () {
    $lead = Lead::factory()->create();
    $user = User::factory()->create();

    $note = $this->service->createNote($lead, $user, 'Private note', true);

    expect($note->is_private)->toBeTrue();
});

test('can create a note with custom type', function () {
    $lead = Lead::factory()->create();
    $user = User::factory()->create();

    $note = $this->service->createNote($lead, $user, 'Call log', false, 'call_log');

    expect($note->type)->toBe('call_log');
});

test('can update a note', function () {
    $note = LeadNote::factory()->create(['content' => 'Original content']);

    $updated = $this->service->updateNote($note, 'Updated content');

    expect($updated->content)->toBe('Updated content');
});

test('can delete a note', function () {
    $note = LeadNote::factory()->create();

    $result = $this->service->deleteNote($note);

    expect($result)->toBeTrue()
        ->and(LeadNote::find($note->id))->toBeNull();
});

test('can get notes for a lead', function () {
    $lead = Lead::factory()->create();
    $user = User::factory()->create();

    LeadNote::factory()->count(3)->create([
        'lead_id' => $lead->id,
        'is_private' => false,
    ]);

    $notes = $this->service->getNotesForLead($lead, $user);

    expect($notes)->toHaveCount(3);
});

test('filters private notes based on user permissions', function () {
    $lead = Lead::factory()->create();
    $author = User::factory()->create();
    $otherUser = User::factory()->create();

    LeadNote::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $author->id,
        'is_private' => true,
    ]);

    LeadNote::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $author->id,
        'is_private' => false,
    ]);

    // Author can see both
    $authorNotes = $this->service->getNotesForLead($lead, $author);
    expect($authorNotes)->toHaveCount(2);

    // Other user can only see public
    $otherNotes = $this->service->getNotesForLead($lead, $otherUser);
    expect($otherNotes)->toHaveCount(1)
        ->and($otherNotes->first()->is_private)->toBeFalse();
});

test('super admin can see all private notes', function () {
    $lead = Lead::factory()->create();
    $author = User::factory()->create();
    $superAdmin = User::factory()->create();

    $superAdminRole = Role::factory()->create(['slug' => 'super_admin']);
    $superAdmin->role()->associate($superAdminRole);
    $superAdmin->save();

    LeadNote::factory()->create([
        'lead_id' => $lead->id,
        'user_id' => $author->id,
        'is_private' => true,
    ]);

    $notes = $this->service->getNotesForLead($lead, $superAdmin);

    expect($notes)->toHaveCount(1)
        ->and($notes->first()->is_private)->toBeTrue();
});

test('can get notes by type', function () {
    $lead = Lead::factory()->create();
    $user = User::factory()->create();

    LeadNote::factory()->count(2)->create([
        'lead_id' => $lead->id,
        'type' => 'call_log',
    ]);

    LeadNote::factory()->create([
        'lead_id' => $lead->id,
        'type' => 'comment',
    ]);

    $callLogs = $this->service->getNotesByType($lead, 'call_log', $user);

    expect($callLogs)->toHaveCount(2)
        ->and($callLogs->every(fn ($note) => $note->type === 'call_log'))->toBeTrue();
});

test('returns only public notes when no user provided', function () {
    $lead = Lead::factory()->create();
    $user = User::factory()->create();

    LeadNote::factory()->create([
        'lead_id' => $lead->id,
        'is_private' => true,
    ]);

    LeadNote::factory()->create([
        'lead_id' => $lead->id,
        'is_private' => false,
    ]);

    $notes = $this->service->getNotesForLead($lead, null);

    expect($notes)->toHaveCount(1)
        ->and($notes->first()->is_private)->toBeFalse();
});
