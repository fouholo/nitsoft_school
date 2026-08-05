<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Attendance\Events\StudentMarkedAbsent;
use App\Domain\Notifications\Contracts\SmsProviderInterface;
use App\Domain\Notifications\Listeners\NotifyGuardiansOfAbsence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsProviderInterface::class, function (): SmsProviderInterface {
            $driver = config('sms.default');
            $class = config("sms.providers.{$driver}");

            return $this->app->make($class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Compatibilité MySQL/MariaDB : évite "key too long" sur les index
        // uniques utf8mb4 (ex: users.email) selon la configuration du serveur.
        SchemaBuilder::defaultStringLength(191);

        // Les modèles vivent sous App\Domain\{X}\Models\... ; la convention
        // Laravel par défaut chercherait la factory sous le même sous-namespace
        // imbriqué. On la fait toujours résoudre vers database/factories à plat.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Bypass global et unique pour le Super Admin SaaS — toute autre
        // vérification d'autorisation passe par les Policies par domaine.
        Gate::before(fn (User $user, string $ability): ?bool => $user->isSuperAdmin() ? true : null);

        Event::listen(StudentMarkedAbsent::class, NotifyGuardiansOfAbsence::class);
    }
}
