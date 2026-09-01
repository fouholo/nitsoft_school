<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Backup\Index;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('un saas admin secondaire voit l’export mais pas vider/restaurer', function () {
    $second = createSaasAdmin('second');

    Livewire::actingAs($second)
        ->test(Index::class)
        ->assertSee(__('Exporter'))
        ->assertDontSee(__('Vider'))
        ->assertDontSee(__('Restaurer'));
});

test('un saas admin secondaire ne peut pas appeler wipe ou import directement', function () {
    $second = createSaasAdmin('second');

    Livewire::actingAs($second)
        ->test(Index::class)
        ->set('wipeConfirmationWord', 'VIDER')
        ->call('wipe')
        ->assertForbidden();
});

test('un utilisateur non saas admin ne peut pas accéder à l’écran', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');

    Livewire::actingAs($directeur)
        ->test(Index::class)
        ->assertForbidden();
});

test('le bouton vider reste inopérant tant que le mot de confirmation n’est pas exact', function () {
    $main = createSaasAdmin('main');
    Establishment::factory()->create();

    Livewire::actingAs($main)
        ->test(Index::class)
        ->set('wipeConfirmationWord', 'pas le bon mot')
        ->call('wipe')
        ->assertHasErrors(['wipeConfirmationWord']);

    expect(DB::table('establishments')->count())->toBe(1);
});

test('un saas admin principal peut vider avec le mot de confirmation exact', function () {
    $main = createSaasAdmin('main');
    Establishment::factory()->create();

    Livewire::actingAs($main)
        ->test(Index::class)
        ->set('wipeScope', 'table')
        ->set('wipeTable', 'establishments')
        ->set('wipeConfirmationWord', 'VIDER')
        ->call('wipe')
        ->assertHasNoErrors();

    expect(DB::table('establishments')->count())->toBe(0);
});

test('l’upload d’un fichier au mauvais mimetype est rejeté', function () {
    $main = createSaasAdmin('main');

    Livewire::actingAs($main)
        ->test(Index::class)
        ->set('importConfirmationWord', 'RESTAURER')
        ->set('archive', UploadedFile::fake()->create('archive.txt', 10))
        ->call('import')
        ->assertHasErrors(['archive']);
});
