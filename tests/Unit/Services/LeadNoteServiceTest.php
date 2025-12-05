<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadNoteService;
use Illuminate\Support\Facades\Config;

describe('LeadNoteService', function () {
    beforeEach(function () {
        $this->service = new LeadNoteService();
    });

    describe('createNote', function () {
        test('creates a note successfully', function () {
            $lead = Lead::factory()->create();
            $user = User::factory()->create();

            $note = $this->service->createNote($lead, $user, 'Test note content', false, 'comment');

            expect($note)->toBeInstanceOf(LeadNote::class)
                ->and($note->lead_id)->toBe($lead->id)
                ->and($note->user_id)->toBe($user->id)
                ->and($note->content)->toBe('Test note content')
                ->and($note->is_private)->toBeFalse()
                ->and($note->type)->toBe('comment');
        });

        test('creates private note', function () {
            $lead = Lead::factory()->create();
            $user = User::factory()->create();

            $note = $this->service->createNote($lead, $user, 'Private note', true);

            expect($note->is_private)->toBeTrue();
        });

        test('recalculates lead score when configured', function () {
            Config::set('lead-scoring.auto_recalculate.on_note_added', true);

            $lead = Lead::factory()->create(['score' => 0]);
            $user = User::factory()->create();

            $note = $this->service->createNote($lead, $user, 'Test note');

            // Score should be recalculated (may still be 0 but should be calculated)
            $freshLead = $lead->fresh();
            expect($freshLead->score)->not->toBeNull()
                ->and($freshLead->score_updated_at)->not->toBeNull();
        });
    });

    describe('updateNote', function () {
        test('updates note content', function () {
            $note = LeadNote::factory()->create(['content' => 'Old content']);

            $updated = $this->service->updateNote($note, 'New content');

            expect($updated->content)->toBe('New content');
        });
    });

    describe('deleteNote', function () {
        test('deletes note', function () {
            $note = LeadNote::factory()->create();

            $result = $this->service->deleteNote($note);

            expect($result)->toBeTrue()
                ->and(LeadNote::find($note->id))->toBeNull();
        });
    });

    describe('getNotesForLead', function () {
        test('returns all notes for lead when user is super admin', function () {
            $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
            $user = User::factory()->create(['role_id' => $role->id]);
            $lead = Lead::factory()->create();

            LeadNote::factory()->create([
                'lead_id' => $lead->id,
                'is_private' => false,
            ]);
            LeadNote::factory()->create([
                'lead_id' => $lead->id,
                'is_private' => true,
                'user_id' => User::factory()->create()->id,
            ]);

            $notes = $this->service->getNotesForLead($lead, $user);

            expect($notes->count())->toBe(2);
        });

        test('filters private notes for regular users', function () {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $lead = Lead::factory()->create();

            LeadNote::factory()->create([
                'lead_id' => $lead->id,
                'is_private' => false,
            ]);
            LeadNote::factory()->create([
                'lead_id' => $lead->id,
                'is_private' => true,
                'user_id' => $otherUser->id,
            ]);
            LeadNote::factory()->create([
                'lead_id' => $lead->id,
                'is_private' => true,
                'user_id' => $user->id,
            ]);

            $notes = $this->service->getNotesForLead($lead, $user);

            expect($notes->count())->toBe(2); // Public + own private
        });

        test('returns only public notes when no user provided', function () {
            $lead = Lead::factory()->create();

            LeadNote::factory()->create([
                'lead_id' => $lead->id,
                'is_private' => false,
            ]);
            LeadNote::factory()->create([
                'lead_id' => $lead->id,
                'is_private' => true,
            ]);

            $notes = $this->service->getNotesForLead($lead);

            expect($notes->count())->toBe(1)
                ->and($notes->first()->is_private)->toBeFalse();
        });
    });

    describe('getNotesByType', function () {
        test('returns notes filtered by type', function () {
            $lead = Lead::factory()->create();
            $user = User::factory()->create();

            LeadNote::factory()->create([
                'lead_id' => $lead->id,
                'type' => 'comment',
            ]);
            LeadNote::factory()->create([
                'lead_id' => $lead->id,
                'type' => 'call',
            ]);

            $notes = $this->service->getNotesByType($lead, 'comment', $user);

            expect($notes->count())->toBe(1)
                ->and($notes->first()->type)->toBe('comment');
        });
    });
});

