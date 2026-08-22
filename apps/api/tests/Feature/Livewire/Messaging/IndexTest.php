<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Messaging\Models\Conversation;
use App\Livewire\Messaging\Index;
use Livewire\Livewire;

test('deux membres actifs du même établissement démarrent une conversation directe et échangent des messages', function () {
    $establishment = Establishment::factory()->create();
    $alice = createUserWithRole($establishment, 'directeur');
    $bob = createUserWithRole($establishment, 'enseignant');
    test()->actingAs($alice);

    Livewire::test(Index::class, ['conversation' => null])
        ->call('toggleParticipant', $bob->id)
        ->call('startConversation')
        ->assertRedirect();

    $conversation = Conversation::sole();

    expect($conversation->type)->toBe('direct')
        ->and($conversation->participants->pluck('id')->sort()->values()->all())->toBe(collect([$alice->id, $bob->id])->sort()->values()->all());

    Livewire::test(Index::class, ['conversation' => $conversation])
        ->set('newMessageBody', 'Bonjour Bob')
        ->call('sendMessage')
        ->assertHasNoErrors();

    expect($conversation->messages()->sole()->body)->toBe('Bonjour Bob')
        ->and($conversation->messages()->sole()->sender_id)->toBe($alice->id);
});

test('redémarrer une conversation directe avec la même personne réutilise la conversation existante', function () {
    $establishment = Establishment::factory()->create();
    $alice = createUserWithRole($establishment, 'directeur');
    $bob = createUserWithRole($establishment, 'enseignant');
    test()->actingAs($alice);

    Livewire::test(Index::class, ['conversation' => null])
        ->call('toggleParticipant', $bob->id)
        ->call('startConversation');

    Livewire::test(Index::class, ['conversation' => null])
        ->call('toggleParticipant', $bob->id)
        ->call('startConversation');

    expect(Conversation::count())->toBe(1);
});

test('crée un groupe nommé à 3 participants', function () {
    $establishment = Establishment::factory()->create();
    $alice = createUserWithRole($establishment, 'directeur');
    $bob = createUserWithRole($establishment, 'enseignant');
    $carol = createUserWithRole($establishment, 'caissier');
    test()->actingAs($alice);

    Livewire::test(Index::class, ['conversation' => null])
        ->call('toggleParticipant', $bob->id)
        ->call('toggleParticipant', $carol->id)
        ->set('groupName', 'Direction')
        ->call('startConversation')
        ->assertRedirect();

    $conversation = Conversation::sole();

    expect($conversation->type)->toBe('group')
        ->and($conversation->name)->toBe('Direction')
        ->and($conversation->participants)->toHaveCount(3);
});

test('un groupe sans nom affiche la liste des autres participants', function () {
    $establishment = Establishment::factory()->create();
    $alice = createUserWithRole($establishment, 'directeur');
    $bob = createUserWithRole($establishment, 'enseignant');
    $carol = createUserWithRole($establishment, 'caissier');
    test()->actingAs($alice);

    Livewire::test(Index::class, ['conversation' => null])
        ->call('toggleParticipant', $bob->id)
        ->call('toggleParticipant', $carol->id)
        ->call('startConversation');

    $conversation = Conversation::sole();

    expect($conversation->displayName($alice))->toBe(collect([$bob->name, $carol->name])->implode(', '));
});

test('un tiers non-participant ne peut pas accéder à une conversation', function () {
    $establishment = Establishment::factory()->create();
    $alice = createUserWithRole($establishment, 'directeur');
    $bob = createUserWithRole($establishment, 'enseignant');
    $outsider = createUserWithRole($establishment, 'caissier');

    test()->actingAs($alice);
    Livewire::test(Index::class, ['conversation' => null])->call('toggleParticipant', $bob->id)->call('startConversation');
    $conversation = Conversation::sole();

    test()->actingAs($outsider);
    Livewire::test(Index::class, ['conversation' => $conversation])->assertForbidden();
});

test('un fondateur peut démarrer une conversation avec le personnel d’une autre école de son groupe', function () {
    $foundation = Foundation::factory()->create();
    Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $establishmentB = Establishment::factory()->create(['foundation_id' => $foundation->id]);

    $founder = createFounder($foundation);
    $teacherB = createUserWithRole($establishmentB, 'enseignant');

    test()->actingAs($founder);

    Livewire::test(Index::class, ['conversation' => null])
        ->call('toggleParticipant', $teacherB->id)
        ->call('startConversation')
        ->assertRedirect();

    expect(Conversation::sole()->participants->pluck('id'))->toContain($teacherB->id);
});

test('un enseignant sans accès à une autre école du groupe ne peut pas lui écrire', function () {
    $foundation = Foundation::factory()->create();
    $establishmentA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $establishmentB = Establishment::factory()->create(['foundation_id' => $foundation->id]);

    $teacherA = createUserWithRole($establishmentA, 'enseignant');
    $teacherB = createUserWithRole($establishmentB, 'enseignant');

    test()->actingAs($teacherA);

    Livewire::test(Index::class, ['conversation' => null])
        ->call('toggleParticipant', $teacherB->id)
        ->call('startConversation')
        ->assertStatus(422);

    expect(Conversation::count())->toBe(0);
});

test('ouvrir une conversation met à jour last_read_at et fait retomber le compteur de non-lus', function () {
    $establishment = Establishment::factory()->create();
    $alice = createUserWithRole($establishment, 'directeur');
    $bob = createUserWithRole($establishment, 'enseignant');

    test()->actingAs($alice);
    Livewire::test(Index::class, ['conversation' => null])->call('toggleParticipant', $bob->id)->call('startConversation');
    $conversation = Conversation::sole();

    Livewire::test(Index::class, ['conversation' => $conversation])
        ->set('newMessageBody', 'Salut')
        ->call('sendMessage');

    expect($conversation->unreadCountFor($bob))->toBe(1);

    Livewire::actingAs($bob);
    Livewire::test(Index::class, ['conversation' => $conversation]);

    expect($conversation->unreadCountFor($bob))->toBe(0);
});

test('validation : message vide rejeté, conversation sans participant rejetée', function () {
    $establishment = Establishment::factory()->create();
    $alice = createUserWithRole($establishment, 'directeur');
    $bob = createUserWithRole($establishment, 'enseignant');
    test()->actingAs($alice);

    Livewire::test(Index::class, ['conversation' => null])
        ->call('startConversation')
        ->assertHasErrors(['selectedParticipantIds']);

    Livewire::test(Index::class, ['conversation' => null])
        ->call('toggleParticipant', $bob->id)
        ->call('startConversation');

    $conversation = Conversation::sole();

    Livewire::test(Index::class, ['conversation' => $conversation])
        ->set('newMessageBody', '')
        ->call('sendMessage')
        ->assertHasErrors(['newMessageBody']);
});
