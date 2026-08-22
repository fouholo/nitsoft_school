<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Livewire\Messaging\UnreadBadge;
use Livewire\Livewire;

test('le badge affiche le nombre de conversations avec des messages non lus, puis retombe à 0 après lecture', function () {
    $establishment = Establishment::factory()->create();
    $alice = createUserWithRole($establishment, 'directeur');
    $bob = createUserWithRole($establishment, 'enseignant');

    $conversation = Conversation::create(['type' => 'direct', 'created_by' => $bob->id]);
    $conversation->participants()->attach([$alice->id, $bob->id]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $bob->id,
        'body' => 'Bonjour Alice',
    ]);

    test()->actingAs($alice);

    Livewire::test(UnreadBadge::class)
        ->assertSee('bg-orange-700');

    $conversation->participants()->updateExistingPivot($alice->id, ['last_read_at' => now()]);

    Livewire::test(UnreadBadge::class)
        ->assertDontSee('bg-orange-700');
});
